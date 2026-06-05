<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DTOs;

use Spatie\LaravelData\Data;

class CriarRedeData extends Data
{
    public function __construct(
        public string $nome,
        public ?int $plano_id = null,
    ) {}
}
