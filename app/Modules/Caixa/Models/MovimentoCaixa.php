<?php

namespace App\Modules\Caixa\Models;

use App\Enums\FormaPagamento;
use App\Enums\TipoMovimentoCaixa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $caixa_id
 * @property TipoMovimentoCaixa $tipo
 * @property float $valor
 * @property string $descricao
 * @property FormaPagamento|null $forma_pagamento
 * @property int|null $baixa_pagamento_id
 * @property int|null $baixa_despesa_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Caixa $caixa
 * @property-read BaixaPagamento|null $baixaPagamento
 * @property-read BaixaDespesa|null $baixaDespesa
 */
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
