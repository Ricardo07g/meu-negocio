<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Tenant\Models\Rede;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait RedeTrait
{
    public static function bootRedeTrait(): void
    {
        static::addGlobalScope('rede', function (Builder $query) {
            if ($usuario = static::resolverUsuarioAutenticado()) {
                $query->where($query->getModel()->getTable().'.rede_id', $usuario->rede_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->rede_id) {
                if ($usuario = static::resolverUsuarioAutenticado()) {
                    $model->rede_id = $usuario->rede_id;
                }
            }
        });
    }

    public function rede(): BelongsTo
    {
        return $this->belongsTo(Rede::class, 'rede_id');
    }

    protected static function resolverUsuarioAutenticado(): mixed
    {
        static $resolvendo = false;

        if ($resolvendo) {
            return null;
        }

        $resolvendo = true;

        try {
            $usuario = auth()->user();
        } finally {
            $resolvendo = false;
        }

        return $usuario;
    }
}
