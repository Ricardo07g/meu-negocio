<?php

namespace App\Modules\Tenant\Models;

use App\Enums\StatusRede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
