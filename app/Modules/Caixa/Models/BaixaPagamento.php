<?php

namespace App\Modules\Caixa\Models;

use App\Enums\FormaPagamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BaixaPagamento extends Model
{
    use PertenceARede, PertenceAEmpresa;

    protected $table = 'baixas_pagamento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'pagamento_id',
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

    public function pagamento(): BelongsTo
    {
        return $this->belongsTo(Pagamento::class, 'pagamento_id');
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_id');
    }

    public function movimentoCaixa(): HasOne
    {
        return $this->hasOne(MovimentoCaixa::class, 'baixa_pagamento_id');
    }
}
