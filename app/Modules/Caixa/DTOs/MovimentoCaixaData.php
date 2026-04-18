<?php

namespace App\Modules\Caixa\DTOs;

use Spatie\LaravelData\Data;

class MovimentoCaixaData extends Data
{
    public function __construct(
        public float $valor,
        public string $descricao,
    ) {}
}
