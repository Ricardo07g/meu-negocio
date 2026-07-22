<?php

declare(strict_types=1);

namespace App\Modules\Conta\Models;

use App\Enums\TipoLancamento;
use App\Models\BaseModel;
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa};
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lancamento no razao de uma conta: toda entrada (credito) ou saida (debito) de
 * dinheiro. Substitui o antigo MovimentoCaixa, agora tenant-aware (rede/empresa)
 * e ligado a uma Conta. Append-only (sem SoftDeletes). Ver ADR-0010.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $conta_id
 * @property int|null $caixa_id
 * @property TipoLancamento $tipo
 * @property string $categoria
 * @property string $valor
 * @property Carbon $data
 * @property string $descricao
 * @property string|null $forma_pagamento_nome
 * @property int|null $baixa_pagamento_id
 * @property int|null $baixa_despesa_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Lancamento extends BaseModel
{
    use EmpresaTrait;

    protected $table = 'lancamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'conta_id',
        'caixa_id',
        'tipo',
        'categoria',
        'valor',
        'data',
        'descricao',
        'forma_pagamento_nome',
        'baixa_pagamento_id',
        'baixa_despesa_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoLancamento::class,
            'valor' => 'decimal:2',
            'data' => 'date',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'conta_id');
    }

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
