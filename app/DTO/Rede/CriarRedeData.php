<?php

namespace App\DTO\Rede;

use Spatie\LaravelData\Data;

class CriarRedeData extends Data
{
    public function __construct(
        public string $nome,
        public ?int $plano_id = null,
    ) {}
}
