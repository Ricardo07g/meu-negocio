<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DTOs;

use Spatie\LaravelData\Data;

class EmpresaData extends Data
{
    public function __construct(
        public string $nome,
        public ?string $documento = null,
        public ?string $telefone = null,
        public ?string $email = null,
    ) {}
}
