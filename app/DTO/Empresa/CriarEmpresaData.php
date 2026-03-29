<?php

namespace App\DTO\Empresa;

use Spatie\LaravelData\Data;

class CriarEmpresaData extends Data
{
    public function __construct(
        public string $nome,
        public ?string $documento = null,
        public ?string $telefone = null,
        public ?string $email = null,
    ) {}
}
