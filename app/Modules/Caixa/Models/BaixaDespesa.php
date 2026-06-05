<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Models;

use App\Enums\FormaPagamento;
use App\Models\BaseModel;
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $parcela_despesa_id
 * @property int|null $caixa_id
 * @property float $valor
 * @property float $multa
 * @property float $juros
 * @property float $desconto
 * @property FormaPagamento $forma_pagamento
 * @property Carbon $data
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ParcelaDespesa $parcela
 * @property-read Caixa|null $caixa
 * @property-read MovimentoCaixa|null $movimentoCaixa
 */
class BaixaDespesa extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'baixas_despesa';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'parcela_despesa_id',
        'caixa_id',
        'valor',
        'multa',
        'juros',
        'desconto',
        'forma_pagamento',
        'data',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'multa' => 'decimal:2',
            'juros' => 'decimal:2',
            'desconto' => 'decimal:2',
            'data' => 'datetime',
            'forma_pagamento' => FormaPagamento::class,
        ];
    }

    /** Valor líquido que sai do caixa: principal + multa + juros - desconto. */
    public function valorTotal(): float
    {
        return (float) ($this->valor + $this->multa + $this->juros - $this->desconto);
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(ParcelaDespesa::class, 'parcela_despesa_id');
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
