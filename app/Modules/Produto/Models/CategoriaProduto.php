<?php

namespace App\Modules\Produto\Models;

use App\Traits\PertenceARede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProduto extends Model
{
    use PertenceARede;

    protected $table = 'categorias_produto';

    protected $fillable = [
        'rede_id',
        'nome',
        'descricao',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria_produto_id');
    }
}
