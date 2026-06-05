<?php

declare(strict_types=1);

namespace App\Modules\Venda\Models;

use App\Modules\Produto\Models\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venda_produto_id
 * @property int $produto_id
 * @property string $descricao
 * @property int $quantidade
 * @property float $valor_unitario
 * @property float $desconto
 * @property float $acrescimo
 * @property float $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VendaProduto $vendaProduto
 * @property-read Produto $produto
 */
class VendaProdutoItem extends Model
{
    protected $table = 'venda_produto_itens';

    protected $fillable = [
        'venda_produto_id',
        'produto_id',
        'descricao',
        'quantidade',
        'valor_unitario',
        'desconto',
        'acrescimo',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function vendaProduto(): BelongsTo
    {
        return $this->belongsTo(VendaProduto::class, 'venda_produto_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
