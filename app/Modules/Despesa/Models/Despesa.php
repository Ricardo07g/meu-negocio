<?php

namespace App\Modules\Despesa\Models;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Enums\StatusDespesa;
use App\Enums\StatusParcela;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int|null $categoria_despesa_id
 * @property string $nome
 * @property string|null $fornecedor_nome
 * @property string|null $documento
 * @property string|null $observacoes
 * @property float $valor_total
 * @property CondicaoPagamento $condicao_pagamento
 * @property FormaRecebimentoPrazo|null $forma_recebimento_prazo
 * @property Carbon $mes_referencia
 * @property Carbon $data_emissao
 * @property StatusDespesa $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CategoriaDespesa|null $categoria
 * @property-read Collection<int, ParcelaDespesa> $parcelas
 * @property-read Collection<int, BaixaDespesa> $baixas
 */
class Despesa extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'categoria_despesa_id',
        'nome',
        'fornecedor_nome',
        'documento',
        'observacoes',
        'valor_total',
        'condicao_pagamento',
        'forma_recebimento_prazo',
        'mes_referencia',
        'data_emissao',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'mes_referencia' => 'date',
            'data_emissao' => 'date',
            'condicao_pagamento' => CondicaoPagamento::class,
            'forma_recebimento_prazo' => FormaRecebimentoPrazo::class,
            'status' => StatusDespesa::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDespesa::class, 'categoria_despesa_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(ParcelaDespesa::class, 'despesa_id')->orderBy('numero');
    }

    public function baixas(): HasManyThrough
    {
        return $this->hasManyThrough(
            BaixaDespesa::class,
            ParcelaDespesa::class,
            'despesa_id',
            'parcela_despesa_id',
        );
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    /** Principal abatido das parcelas (base do saldo devedor). */
    public function valorPago(): float
    {
        return (float) $this->parcelas->sum('valor_pago');
    }

    /**
     * Total líquido que efetivamente saiu do caixa:
     * valor principal + multa + juros − desconto de cada baixa.
     */
    public function totalPagoLiquido(): float
    {
        $total = 0;
        foreach ($this->parcelas as $parcela) {
            foreach ($parcela->baixas as $baixa) {
                $total += $baixa->valorTotal();
            }
        }

        return (float) $total;
    }

    public function saldoRestante(): float
    {
        $ativo = $this->parcelas
            ->whereNotIn('status', [StatusParcela::Cancelado, StatusParcela::Renegociado])
            ->sum('valor');

        return (float) max($ativo - $this->valorPago(), 0);
    }

    public function proximaParcela(): ?ParcelaDespesa
    {
        return $this->parcelas
            ->where('status', StatusParcela::Pendente)
            ->sortBy('data_vencimento')
            ->first();
    }

    public function parcelasPagas(): int
    {
        return $this->parcelas->where('status', StatusParcela::Pago)->count();
    }

    public function recalcularStatus(): void
    {
        $parcelas = $this->parcelas()->get();
        if ($parcelas->isEmpty()) {
            return;
        }

        $ativas = $parcelas->reject(fn ($p) => $p->status === StatusParcela::Cancelado);

        if ($ativas->isEmpty()) {
            $this->update(['status' => StatusDespesa::Cancelada]);

            return;
        }

        $pagas = $ativas->filter(fn ($p) => $p->status === StatusParcela::Pago)->count();

        if ($pagas === $ativas->count()) {
            $this->update(['status' => StatusDespesa::Paga]);
        } elseif ($pagas > 0) {
            $this->update(['status' => StatusDespesa::Parcial]);
        } else {
            $this->update(['status' => StatusDespesa::Pendente]);
        }
    }
}
