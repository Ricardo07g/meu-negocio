<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Faixa de taxa por numero de parcelas de uma FormaPagamento (cartao de credito).
 *
 * @property int $id
 * @property int $rede_id
 * @property int $forma_pagamento_id
 * @property int $parcela_min
 * @property int $parcela_max
 * @property string $taxa_percentual
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FormaPagamento $forma
 */
class FormaPagamentoTaxa extends BaseModel
{
    protected $table = 'formas_pagamento_taxas';

    protected $fillable = [
        'rede_id',
        'forma_pagamento_id',
        'parcela_min',
        'parcela_max',
        'taxa_percentual',
    ];

    protected function casts(): array
    {
        return [
            'parcela_min' => 'integer',
            'parcela_max' => 'integer',
            'taxa_percentual' => 'decimal:2',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function forma(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }
}
