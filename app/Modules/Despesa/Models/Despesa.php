<?php

namespace App\Modules\Despesa\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusDespesa;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'categoria_despesa_id',
        'nome',
        'fornecedor_nome',
        'documento',
        'observacoes',
        'valor',
        'valor_pago',
        'forma_pagamento',
        'data_emissao',
        'data_vencimento',
        'competencia',
        'status',
        'grupo_parcelamento_id',
        'parcela_numero',
        'parcela_total',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'data_emissao' => 'date',
            'data_vencimento' => 'date',
            'competencia' => 'date',
            'status' => StatusDespesa::class,
            'forma_pagamento' => FormaPagamento::class,
            'parcela_numero' => 'integer',
            'parcela_total' => 'integer',
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

    public function baixas(): HasMany
    {
        return $this->hasMany(BaixaDespesa::class, 'despesa_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function saldoRestante(): float
    {
        return (float) $this->valor - (float) $this->valor_pago;
    }

    public function estaVencida(): bool
    {
        return $this->status === StatusDespesa::Pendente
            && $this->data_vencimento
            && $this->data_vencimento->isPast();
    }
}
