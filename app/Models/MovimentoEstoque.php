<?php

namespace App\Models;

use App\Enums\TipoMovimentoEstoque;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentoEstoque extends Model
{
    use PertenceARede, PertenceAEmpresa;

    protected $table = 'movimentos_estoque';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'produto_id',
        'tipo',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentoEstoque::class,
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
