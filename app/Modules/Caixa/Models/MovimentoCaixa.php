<?php

namespace App\Modules\Caixa\Models;

use App\Enums\FormaPagamento;
use App\Enums\TipoMovimentoCaixa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentoCaixa extends Model
{
    protected $table = 'movimentos_caixa';

    protected $fillable = [
        'caixa_id',
        'tipo',
        'valor',
        'descricao',
        'forma_pagamento',
        'baixa_pagamento_id',
        'baixa_despesa_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentoCaixa::class,
            'valor' => 'decimal:2',
            'forma_pagamento' => FormaPagamento::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_id');
    }

    public function baixaPagamento(): BelongsTo
    {
        return $this->belongsTo(BaixaPagamento::class, 'baixa_pagamento_id');
    }

    public function baixaDespesa(): BelongsTo
    {
        return $this->belongsTo(BaixaDespesa::class, 'baixa_despesa_id');
    }
}
