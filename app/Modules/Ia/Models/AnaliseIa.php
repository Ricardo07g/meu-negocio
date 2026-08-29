<?php

declare(strict_types=1);

namespace App\Modules\Ia\Models;

use App\Models\BaseModel;
use App\Modules\Ia\Enums\{StatusAnalise, TipoAnalise};
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Registro de uma analise por IA: cache, historico e razao de consumo na mesma linha.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int|null $usuario_id
 * @property string $analisavel_type
 * @property int $analisavel_id
 * @property TipoAnalise $tipo
 * @property StatusAnalise $status
 * @property string $hash_entrada
 * @property string $versao_prompt
 * @property string $modelo
 * @property array|null $resultado
 * @property string|null $erro
 * @property int $tokens_entrada
 * @property int $tokens_saida
 * @property float $custo_estimado
 * @property int $duracao_ms
 * @property int $reaproveitacoes
 * @property Carbon|null $ultima_reaproveitacao_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnaliseIa extends BaseModel
{
    use EmpresaTrait;

    protected $table = 'analises_ia';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'usuario_id',
        'analisavel_type',
        'analisavel_id',
        'tipo',
        'status',
        'hash_entrada',
        'versao_prompt',
        'modelo',
        'resultado',
        'erro',
        'tokens_entrada',
        'tokens_saida',
        'custo_estimado',
        'duracao_ms',
        'reaproveitacoes',
        'ultima_reaproveitacao_em',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAnalise::class,
            'status' => StatusAnalise::class,
            'resultado' => 'array',
            'custo_estimado' => 'decimal:6',
            'ultima_reaproveitacao_em' => 'datetime',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function analisavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    /**
     * Analises criadas no dia corrente **do lojista**, nao no dia UTC.
     *
     * O app roda em UTC; contar com `whereDate(created_at, today())` zeraria a cota
     * as 21h de Brasilia. Aqui as bordas do dia sao calculadas no fuso configurado e
     * convertidas para UTC, que e como `created_at` esta gravado.
     */
    public function scopeDoDiaCorrente(Builder $query): Builder
    {
        $fuso = (string) config('ia.fuso');
        $agora = CarbonImmutable::now($fuso);

        return $query->whereBetween('created_at', [
            $agora->startOfDay()->utc(),
            $agora->endOfDay()->utc(),
        ]);
    }

    public function tokensTotais(): int
    {
        return $this->tokens_entrada + $this->tokens_saida;
    }

    /** O cache so vale enquanto a analise nao envelhecer alem da rede de seguranca. */
    public function dentroDaValidade(): bool
    {
        return $this->created_at !== null
            && $this->created_at->greaterThanOrEqualTo(now()->subDays((int) config('ia.cache_dias')));
    }
}
