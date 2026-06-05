<?php

declare(strict_types=1);

namespace App\Modules\Produto\Models;

use App\Models\BaseModel;
use App\Modules\Estoque\Models\MovimentoEstoque;
use Illuminate\Database\Eloquent\{Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property string $nome
 * @property string|null $codigo
 * @property string|null $codigo_barras
 * @property string|null $descricao
 * @property int|null $categoria_produto_id
 * @property float|null $valor_custo
 * @property int $quantidade
 * @property int|null $estoque_minimo
 * @property string|null $unidade
 * @property bool $ativo
 * @property string|null $observacoes
 * @property float $valor_venda
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CategoriaProduto|null $categoria
 * @property-read Collection<int, MovimentoEstoque> $movimentos
 */
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
