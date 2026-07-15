<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Models;

use App\Models\BaseModel;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $parcela_pagamento_id
 * @property int|null $caixa_id
 * @property float $valor
 * @property float $multa
 * @property float $juros
 * @property float $desconto
 * @property int|null $forma_pagamento_id
 * @property string|null $forma_pagamento_nome
 * @property Carbon $data
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ParcelaPagamento $parcela
 * @property-read FormaPagamento|null $formaPagamento
 * @property-read Caixa|null $caixa
 * @property-read Collection<int, MovimentoCaixa> $movimentos
 * @property-read Collection<int, Recebivel> $recebiveis
 */
class BaixaPagamento extends BaseModel
{
    use EmpresaTrait;

    protected $table = 'baixas_pagamento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'parcela_pagamento_id',
        'caixa_id',
        'valor',
        'multa',
        'juros',
        'desconto',
        'forma_pagamento_id',
        'forma_pagamento_nome',
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
        ];
    }

    /** Valor líquido que entra no caixa: principal + multa + juros - desconto. */
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
        return $this->belongsTo(ParcelaPagamento::class, 'parcela_pagamento_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_id');
    }

    /**
     * Movimentos de caixa ligados a esta baixa: a entrada do recebimento e,
     * se a venda for cancelada, a saída de estorno.
     */
    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoCaixa::class, 'baixa_pagamento_id');
    }

    /** Recebíveis gerados por esta baixa (quando a forma é de cartão). */
    public function recebiveis(): HasMany
    {
        return $this->hasMany(Recebivel::class, 'baixa_pagamento_id');
    }
}
