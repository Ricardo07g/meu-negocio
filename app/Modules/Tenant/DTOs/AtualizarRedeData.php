<?php

namespace App\Modules\Tenant\DTOs;

use Spatie\LaravelData\Data;

class AtualizarRedeData extends Data
{
    public function __construct(
        public string $nome,
    ) {}
}
