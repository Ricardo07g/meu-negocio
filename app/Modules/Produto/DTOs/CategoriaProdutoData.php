<?php

declare(strict_types=1);

namespace App\Modules\Produto\DTOs;

use Spatie\LaravelData\Data;

class CategoriaProdutoData extends Data
{
    public function __construct(
        public string $descricao,
        public ?bool $ativo = true,
    ) {}
}
