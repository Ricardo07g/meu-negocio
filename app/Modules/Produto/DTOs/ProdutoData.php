<?php

namespace App\Modules\Produto\DTOs;

use Spatie\LaravelData\Data;

class ProdutoData extends Data
{
    public function __construct(
        public string $nome,
        public int $quantidade,
        public float $valor_venda,
        public ?string $codigo = null,
        public ?string $codigo_barras = null,
        public ?string $descricao = null,
        public ?int $categoria_produto_id = null,
        public ?float $valor_custo = null,
        public ?int $estoque_minimo = null,
        public ?string $unidade = null,
        public ?bool $ativo = null,
        public ?string $observacoes = null,
    ) {}
}
