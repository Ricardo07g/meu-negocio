<?php

namespace App\Modules\Usuario\DTOs;

use Spatie\LaravelData\Data;

class AtualizarUsuarioData extends Data
{
    public function __construct(
        public string $nome,
        public string $email,
        public ?string $password = null,
        public ?int $empresa_id = null,
        public ?string $papel = null,
        public ?bool $ativo = null,
        public ?bool $atende = null,
    ) {}
}
