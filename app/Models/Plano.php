<?php

namespace App\Models;

use App\Models\Rede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'max_empresas',
        'max_usuarios',
        'tem_estoque',
        'tem_financeiro',
        'tem_relatorios',
    ];

    protected function casts(): array
    {
        return [
            'tem_estoque' => 'boolean',
            'tem_financeiro' => 'boolean',
            'tem_relatorios' => 'boolean',
        ];
    }

    public function redes(): HasMany
    {
        return $this->hasMany(Rede::class, 'plano_id');
    }
}
