<?php

namespace App\Modules\Venda\Models;

use App\Enums\StatusVendaProduto;
use App\Models\BaseModel;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int|null $cliente_id
 * @property int $usuario_id
 * @property Carbon $data
 * @property float $subtotal
 * @property float $desconto
 * @property float $acrescimo
 * @property float $valor_total
 * @property StatusVendaProduto $status
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Cliente|null $cliente
 * @property-read Usuario $usuario
 * @property-read Collection<int, VendaProdutoItem> $itens
 * @property-read Pagamento|null $pagamento
 */
class VendaProduto extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'vendas_produto';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'data',
        'subtotal',
        'desconto',
        'acrescimo',
        'valor_total',
        'status',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'status' => StatusVendaProduto::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaProdutoItem::class, 'venda_produto_id');
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class, 'venda_produto_id');
    }
}
