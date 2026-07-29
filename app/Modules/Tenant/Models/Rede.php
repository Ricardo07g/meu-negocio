<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use App\Enums\StatusRede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\{Collection, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant raiz. Agrupa as licencas do mesmo dono — o plano mora em cada `Empresa`,
 * nao aqui: a rede nao e a unidade comercial.
 *
 * @property int $id
 * @property string $nome
 * @property StatusRede $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Collection<int, Usuario> $usuarios
 */
class Rede extends Model
{
    use SoftDeletes;

    protected $table = 'redes';

    protected $fillable = [
        'nome',
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

    /** @return HasMany<Empresa, $this> */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'rede_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rede_id');
    }
}
