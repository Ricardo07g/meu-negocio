<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use App\Enums\StatusFatura;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $plano_id
 * @property string $referencia
 * @property float $valor
 * @property Carbon $vencimento
 * @property Carbon|null $pago_em
 * @property StatusFatura $status
 * @property-read Plano $plano
 */
class Fatura extends BaseModel
{
    use SoftDeletes;

    protected $table = 'faturas';

    protected $fillable = [
        'rede_id',
        'plano_id',
        'referencia',
        'valor',
        'vencimento',
        'pago_em',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
            'status' => StatusFatura::class,
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
