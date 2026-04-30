<?php

namespace App\Modules\Pagamento\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusParcela;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParcelaPagamento extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'parcelas_pagamento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'pagamento_id',
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

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class, 'pagamento_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(BaixaPagamento::class, 'parcela_pagamento_id');
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
     * Total líquido efetivamente recebido nesta parcela
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

    /**
     * Status efetivo para exibição — pendentes com vencimento no passado
     * viram "vencido" sem precisar de job batch.
     */
    public function statusEfetivo(): StatusParcela
    {
        if ($this->estaVencida()) {
            return StatusParcela::Vencido;
        }

        return $this->status;
    }
}
