<?php

namespace App\DTO\Produto;

use Spatie\LaravelData\Data;

class AtualizarProdutoData extends Data
{
    public function __construct(
        public string $nome,
        public int $quantidade,
        public float $valor,
    ) {}
}
