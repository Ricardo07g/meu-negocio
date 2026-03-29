<?php

namespace App\Models;

use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable
{
    use HasRoles, Notifiable, PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'email',
        'password',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function profissional(): HasOne
    {
        return $this->hasOne(Profissional::class, 'usuario_id');
    }
}
