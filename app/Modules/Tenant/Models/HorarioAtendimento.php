<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use App\Models\BaseModel;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma faixa de expediente: "nesta unidade, na terça, atende-se das 08:00 às 18:00".
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int|null $usuario_id
 * @property int $dia_semana
 * @property string $hora_inicio
 * @property string $hora_fim
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Empresa $empresa
 * @property-read Usuario|null $usuario
 */
class HorarioAtendimento extends BaseModel
{
    use EmpresaTrait;

    protected $table = 'horarios_atendimento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'usuario_id',
        'dia_semana',
        'hora_inicio',
        'hora_fim',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** Nomes dos dias na ordem do `dayOfWeek` do Carbon (0 = domingo). */
    public const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    /** Expediente padrão de quem acaba de abrir a unidade: comercial + meio sábado. */
    public const PADRAO = [
        0 => ['08:00', '12:00', false],
        1 => ['08:00', '18:00', true],
        2 => ['08:00', '18:00', true],
        3 => ['08:00', '18:00', true],
        4 => ['08:00', '18:00', true],
        5 => ['08:00', '18:00', true],
        6 => ['08:00', '12:00', true],
    ];

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
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

    /** @param  Builder<HorarioAtendimento>  $query */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** @param  Builder<HorarioAtendimento>  $query */
    public function scopeDaEmpresaToda(Builder $query): Builder
    {
        return $query->whereNull('usuario_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function nomeDoDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? '—';
    }

    /**
     * O horário (H:i) cai dentro desta faixa?
     *
     * `CarbonInterface` de propósito: a agenda trabalha com `Carbon\Carbon` e o
     * Eloquent devolve `Illuminate\Support\Carbon` — tipar por uma das duas
     * rejeitaria a outra em runtime.
     */
    public function contem(CarbonInterface $momento): bool
    {
        $hora = $momento->format('H:i:s');

        return $hora >= $this->horaNormalizada($this->hora_inicio)
            && $hora <= $this->horaNormalizada($this->hora_fim);
    }

    /**
     * MySQL devolve `time` como "08:00:00"; SQLite (testes) devolve o que foi
     * gravado, que pode ser "08:00". Comparar string com string exige o mesmo
     * formato nos dois — senão "08:00" > "08:00:00" e o teste passa no MySQL e
     * falha no CI.
     */
    private function horaNormalizada(string $hora): string
    {
        return substr($hora.':00', 0, 8);
    }
}
