<?php

namespace App\Modules\Produto\DTOs;

use Spatie\LaravelData\Data;

class CriarProdutoData extends Data
{
    public function __construct(
        public string $nome,
        public int $quantidade,
        public float $valor,
    ) {}
}
