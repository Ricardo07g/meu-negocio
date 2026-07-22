<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Models;

use App\Enums\{StatusCaixa, TipoLancamento};
use App\Models\BaseModel;
use App\Modules\Conta\Models\{Conta, Lancamento};
use App\Modules\Usuario\Models\Usuario;
use App\Traits\{EmpresaTrait, RegistraAtividade};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int|null $conta_id
 * @property int $usuario_id
 * @property Carbon $data
 * @property float $saldo_abertura
 * @property float|null $saldo_fechamento
 * @property StatusCaixa $status
 * @property string|null $observacao
 * @property Carbon|null $fechado_em
 * @property int|null $fechado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Usuario $usuario
 * @property-read Usuario|null $fechadoPor
 * @property-read Conta|null $conta
 * @property-read Collection<int, Lancamento> $lancamentos
 */
class Caixa extends BaseModel
{
    use EmpresaTrait;
    use RegistraAtividade;

    protected $table = 'caixas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'conta_id',
        'usuario_id',
        'data',
        'saldo_abertura',
        'saldo_fechamento',
        'status',
        'observacao',
        'fechado_em',
        'fechado_por',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'saldo_abertura' => 'decimal:2',
            'saldo_fechamento' => 'decimal:2',
            'status' => StatusCaixa::class,
            'fechado_em' => 'datetime',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'fechado_por');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'conta_id');
    }

    /** Lancamentos do razao registrados nesta sessao de caixa (a gaveta). */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class, 'caixa_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    /**
     * Saldo da sessao = saldo_abertura + creditos − debitos dos lancamentos
     * desta gaveta (recebimentos/reforcos entram; despesas/sangrias/estornos saem).
     */
    public function saldoCalculado(): float
    {
        $creditos = $this->lancamentos()->where('tipo', TipoLancamento::Credito->value)->sum('valor');
        $debitos = $this->lancamentos()->where('tipo', TipoLancamento::Debito->value)->sum('valor');

        return (float) ($this->saldo_abertura + $creditos - $debitos);
    }
}
