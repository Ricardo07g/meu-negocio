<?php

namespace App\Modules\Produto\Models;

use App\Models\BaseModel;
use App\Modules\Estoque\Models\MovimentoEstoque;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends BaseModel
{
    use SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'rede_id',
        'nome',
        'codigo',
        'codigo_barras',
        'descricao',
        'categoria_produto_id',
        'quantidade',
        'valor_custo',
        'valor_venda',
        'estoque_minimo',
        'unidade',
        'ativo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor_venda' => 'decimal:2',
            'valor_custo' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_produto_id');
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoEstoque::class, 'produto_id');
    }
}
