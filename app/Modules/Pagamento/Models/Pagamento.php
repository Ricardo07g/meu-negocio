<?php

namespace App\Modules\Pagamento\Models;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Enums\StatusPagamento;
use App\Enums\StatusParcela;
use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Models\VendaProduto;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagamento extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'pagamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'agendamento_id',
        'venda_pacote_id',
        'venda_produto_id',
        'valor_total',
        'desconto',
        'acrescimo',
        'condicao_pagamento',
        'forma_recebimento_prazo',
        'mes_referencia',
        'status',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'mes_referencia' => 'date',
            'condicao_pagamento' => CondicaoPagamento::class,
            'forma_recebimento_prazo' => FormaRecebimentoPrazo::class,
            'status' => StatusPagamento::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function vendaPacote(): BelongsTo
    {
        return $this->belongsTo(VendaPacote::class, 'venda_pacote_id');
    }

    public function vendaProduto(): BelongsTo
    {
        return $this->belongsTo(VendaProduto::class, 'venda_produto_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(ParcelaPagamento::class, 'pagamento_id')->orderBy('numero');
    }

    public function baixas(): HasManyThrough
    {
        return $this->hasManyThrough(
            BaixaPagamento::class,
            ParcelaPagamento::class,
            'pagamento_id',
            'parcela_pagamento_id',
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
     * Total líquido que efetivamente entrou no caixa considerando todas as baixas:
     * valor principal + multa + juros − desconto.
     */
    public function totalRecebidoLiquido(): float
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

    public function proximaParcela(): ?ParcelaPagamento
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

    public function parcelasTotalAtivo(): int
    {
        return $this->parcelas->whereNotIn('status', [StatusParcela::Cancelado])->count();
    }

    /**
     * Recalcula o status agregado do título a partir das parcelas e persiste.
     */
    public function recalcularStatus(): void
    {
        $parcelas = $this->parcelas()->get();
        if ($parcelas->isEmpty()) {
            return;
        }

        $ativas = $parcelas->reject(fn ($p) => $p->status === StatusParcela::Cancelado);

        if ($ativas->isEmpty()) {
            $this->update(['status' => StatusPagamento::Cancelado]);

            return;
        }

        $pagas = $ativas->filter(fn ($p) => $p->status === StatusParcela::Pago)->count();
        $pendentes = $ativas->count() - $pagas;

        if ($pagas === $ativas->count()) {
            $this->update(['status' => StatusPagamento::Pago]);
        } elseif ($pagas > 0) {
            $this->update(['status' => StatusPagamento::Parcial]);
        } else {
            $this->update(['status' => StatusPagamento::Pendente]);
        }
    }
}
