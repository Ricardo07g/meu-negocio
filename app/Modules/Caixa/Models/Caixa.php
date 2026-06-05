<?php

namespace App\Modules\Caixa\Models;

use App\Enums\StatusCaixa;
use App\Enums\TipoMovimentoCaixa;
use App\Models\BaseModel;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
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
 * @property-read Collection<int, MovimentoCaixa> $movimentos
 */
class Caixa extends BaseModel
{
    use EmpresaTrait;
    use RegistraAtividade;

    protected $table = 'caixas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
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

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoCaixa::class, 'caixa_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function saldoCalculado(): float
    {
        $entradas = $this->movimentos()
            ->whereIn('tipo', [TipoMovimentoCaixa::Entrada, TipoMovimentoCaixa::Reforco])
            ->sum('valor');

        $saidas = $this->movimentos()
            ->whereIn('tipo', [TipoMovimentoCaixa::Saida, TipoMovimentoCaixa::Sangria])
            ->sum('valor');

        return (float) ($this->saldo_abertura + $entradas - $saidas);
    }
}
