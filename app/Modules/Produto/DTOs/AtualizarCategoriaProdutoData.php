<?php

namespace App\Modules\Produto\DTOs;

use Spatie\LaravelData\Data;

class AtualizarCategoriaProdutoData extends Data
{
    public function __construct(
        public string $nome,
        public ?string $descricao,
    ) {}
}
