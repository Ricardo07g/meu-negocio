<?php

declare(strict_types=1);

namespace App\Modules\Despesa\Models;

use App\Enums\StatusParcela;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\{Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $despesa_id
 * @property int $numero
 * @property int $total
 * @property float $valor
 * @property float $valor_pago
 * @property Carbon $data_vencimento
 * @property Carbon|null $mes_referencia
 * @property int|null $forma_pagamento_id
 * @property string|null $forma_pagamento_nome
 * @property StatusParcela $status
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Despesa $despesa
 * @property-read FormaPagamento|null $formaPagamento
 * @property-read Collection<int, BaixaDespesa> $baixas
 */
class ParcelaDespesa extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'parcelas_despesa';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'despesa_id',
        'numero',
        'total',
        'valor',
        'valor_pago',
        'data_vencimento',
        'mes_referencia',
        'forma_pagamento_id',
        'forma_pagamento_nome',
        'status',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'total' => 'integer',
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'data_vencimento' => 'date',
            'mes_referencia' => 'date',
            'status' => StatusParcela::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class, 'despesa_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(BaixaDespesa::class, 'parcela_despesa_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function saldoRestante(): float
    {
        return (float) max((float) $this->valor - (float) $this->valor_pago, 0);
    }

    /**
     * Total líquido efetivamente pago nesta parcela
     * (soma de valor + multa + juros − desconto de cada baixa).
     */
    public function valorPagoLiquido(): float
    {
        $total = 0;
        foreach ($this->baixas as $baixa) {
            $total += $baixa->valorTotal();
        }

        return (float) $total;
    }

    public function estaVencida(): bool
    {
        return $this->status === StatusParcela::Pendente
            && $this->data_vencimento
            && $this->data_vencimento->isPast();
    }

    public function diasAtraso(): int
    {
        if (! $this->estaVencida()) {
            return 0;
        }

        return (int) $this->data_vencimento->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function statusEfetivo(): StatusParcela
    {
        if ($this->estaVencida()) {
            return StatusParcela::Vencido;
        }

        return $this->status;
    }
}
