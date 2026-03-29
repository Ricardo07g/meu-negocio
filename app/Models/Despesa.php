<?php

namespace App\Models;

use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'valor',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data' => 'date',
        ];
    }
}
