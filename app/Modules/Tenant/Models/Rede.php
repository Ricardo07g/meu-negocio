<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use App\Enums\StatusRede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\{Collection, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property int $plano_id
 * @property int|null $plano_agendado_id
 * @property StatusRede $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Plano $plano
 * @property-read Plano|null $planoAgendado
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Collection<int, Usuario> $usuarios
 * @property-read Collection<int, Usuario> $usuariosAtivos
 */
class Rede extends Model
{
    use SoftDeletes;

    protected $table = 'redes';

    protected $fillable = [
        'nome',
        'plano_id',
        'plano_agendado_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRede::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function planoAgendado(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_agendado_id');
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'rede_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rede_id');
    }

    /**
     * Usuarios que ocupam "vaga" no plano. Inativos (login bloqueado) nao contam
     * no limite, mas mantem seus registros — assim da pra reduzir o uso (e fazer
     * downgrade) sem excluir ninguem.
     */
    public function usuariosAtivos(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rede_id')->where('ativo', true);
    }
}
