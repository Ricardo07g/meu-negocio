<?php

namespace App\Modules\Despesa\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusParcela;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'forma_pagamento',
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
            'forma_pagamento' => FormaPagamento::class,
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
        if (!$this->estaVencida()) {
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
