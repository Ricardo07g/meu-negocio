<?php

namespace App\Modules\Produto\Models;

use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'quantidade',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoEstoque::class, 'produto_id');
    }
}
