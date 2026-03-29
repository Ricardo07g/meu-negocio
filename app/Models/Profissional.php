<?php

namespace App\Models;

use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profissional extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'profissionais';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'usuario_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'profissional_id');
    }
}
