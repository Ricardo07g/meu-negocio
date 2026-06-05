<?php

namespace App\Modules\Tenant\Models;

use App\Enums\StatusRede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property int $plano_id
 * @property StatusRede $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Plano $plano
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Collection<int, Usuario> $usuarios
 */
class Rede extends Model
{
    use SoftDeletes;

    protected $table = 'redes';

    protected $fillable = [
        'nome',
        'plano_id',
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

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'rede_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rede_id');
    }
}
