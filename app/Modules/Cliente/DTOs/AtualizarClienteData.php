<?php

namespace App\Modules\Cliente\DTOs;

use Spatie\LaravelData\Data;

class AtualizarClienteData extends Data
{
    public function __construct(
        public string $nome,
        public ?string $telefone = null,
        public ?string $email = null,
        public ?string $observacoes = null,
    ) {}
}
