<?php

namespace App\Modules\Caixa\Models;

use App\Enums\FormaPagamento;
use App\Models\BaseModel;
use App\Modules\Despesa\Models\Despesa;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaixaDespesa extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'baixas_despesa';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'despesa_id',
        'caixa_id',
        'valor',
        'forma_pagamento',
        'data',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data' => 'datetime',
            'forma_pagamento' => FormaPagamento::class,
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

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_id');
    }

    public function movimentoCaixa(): HasOne
    {
        return $this->hasOne(MovimentoCaixa::class, 'baixa_despesa_id');
    }
}
