<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Models;

use App\Enums\StatusRecebivel;
use App\Models\BaseModel;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\{Builder, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Recebível de cartão: dinheiro a receber do banco/adquirente (D+N, líquido de
 * taxa). Nasce da baixa de uma parcela paga com forma que gera recebível — NÃO
 * entra na gaveta do caixa. O status é derivado pela data (sem job).
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $forma_pagamento_id
 * @property int|null $baixa_pagamento_id
 * @property string $descricao
 * @property float $valor_bruto
 * @property float $taxa_percentual
 * @property float $valor_liquido
 * @property int $parcela_numero
 * @property int $parcela_total
 * @property Carbon $data_venda
 * @property Carbon $data_prevista
 * @property Carbon|null $cancelado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read FormaPagamento $forma
 * @property-read BaixaPagamento|null $baixa
 */
class Recebivel extends BaseModel
{
    use EmpresaTrait, SoftDeletes;

    protected $table = 'recebiveis';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'forma_pagamento_id',
        'baixa_pagamento_id',
        'descricao',
        'valor_bruto',
        'taxa_percentual',
        'valor_liquido',
        'parcela_numero',
        'parcela_total',
        'data_venda',
        'data_prevista',
        'cancelado_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_bruto' => 'decimal:2',
            'taxa_percentual' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'parcela_numero' => 'integer',
            'parcela_total' => 'integer',
            'data_venda' => 'date',
            'data_prevista' => 'date',
            'cancelado_em' => 'datetime',
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

    public function baixa(): BelongsTo
    {
        return $this->belongsTo(BaixaPagamento::class, 'baixa_pagamento_id');
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    /** Não cancelados. */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_em');
    }

    /** Já caíram (data prevista alcançada) e não cancelados. */
    public function scopeRecebidos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_em')->whereDate('data_prevista', '<=', now()->toDateString());
    }

    /** Ainda vão cair (data prevista no futuro) e não cancelados. */
    public function scopePrevistos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_em')->whereDate('data_prevista', '>', now()->toDateString());
    }

    // ███╗   ██╗███████╗ ██████╗  ██████╗  ██████╗██╗ ██████╗
    // ████╗  ██║██╔════╝██╔════╝ ██╔═══██╗██╔════╝██║██╔═══██╗
    // ██╔██╗ ██║█████╗  ██║  ███╗██║   ██║██║     ██║██║   ██║
    // ██║╚██╗██║██╔══╝  ██║   ██║██║   ██║██║     ██║██║   ██║
    // ██║ ╚████║███████╗╚██████╔╝╚██████╔╝╚██████╗██║╚██████╔╝
    // ╚═╝  ╚═══╝╚══════╝ ╚═════╝  ╚═════╝  ╚═════╝╚═╝ ╚═════╝

    /**
     * Status derivado pela data (sem job): cancelado tem prioridade; caso
     * contrário, recebido quando a data prevista chegou, senão previsto.
     */
    public function statusEfetivo(): StatusRecebivel
    {
        if ($this->cancelado_em !== null) {
            return StatusRecebivel::Cancelado;
        }

        if ($this->data_prevista->startOfDay()->lte(now()->startOfDay())) {
            return StatusRecebivel::Recebido;
        }

        return StatusRecebivel::Previsto;
    }
}
