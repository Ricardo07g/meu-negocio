<?php

namespace App\Traits;

use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait EmpresaTrait
{
    public static function bootEmpresaTrait(): void
    {
        static::addGlobalScope('empresa', function (Builder $query) {
            if ($usuario = static::resolverUsuarioSeguro())
            {
                // Admin ve todas as empresas da rede
                if (!$usuario->hasRole('Admin'))
                {
                    $query->where($query->getModel()->getTable() . '.empresa_id', $usuario->empresa_id);
                }
            }
        });

        static::creating(function ($model) {
            if (!$model->empresa_id)
            {
                if ($usuario = static::resolverUsuarioSeguro())
                {
                    $model->empresa_id = $usuario->empresa_id;
                }
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    protected static function resolverUsuarioSeguro(): mixed
    {
        static $resolvendo = false;

        if ($resolvendo)
        {
            return null;
        }

        $resolvendo = true;

        try
        {
            $usuario = auth()->user();
        }
        finally
        {
            $resolvendo = false;
        }

        return $usuario;
    }
}
