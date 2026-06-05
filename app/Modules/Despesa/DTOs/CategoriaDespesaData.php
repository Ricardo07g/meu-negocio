<?php

declare(strict_types=1);

namespace App\Modules\Despesa\DTOs;

use Spatie\LaravelData\Data;

class CategoriaDespesaData extends Data
{
    public function __construct(
        public string $descricao,
        public ?bool $ativo = true,
    ) {}
}
