<?php

namespace App\Modules\Venda\Models;

use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\Produto;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendaProduto extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'vendas_produto';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'produto_id',
        'quantidade',
        'valor_total',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
