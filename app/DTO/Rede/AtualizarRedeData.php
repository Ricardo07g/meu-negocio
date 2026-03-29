<?php

namespace App\DTO\Rede;

use Spatie\LaravelData\Data;

class AtualizarRedeData extends Data
{
    public function __construct(
        public string $nome,
    ) {}
}
