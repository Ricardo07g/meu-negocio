<?php

declare(strict_types=1);

namespace App\Modules\Usuario\DTOs;

use Spatie\LaravelData\Data;

class UsuarioData extends Data
{
    public function __construct(
        public string $nome,
        public string $email,
        public ?string $password = null,
        public ?int $empresa_id = null,
        public ?string $papel = null,
        public ?bool $ativo = null,
        public ?bool $atende = null,
        /** @var array<int>|null IDs das empresas que o usuario pode acessar (pivot empresa_usuario). */
        public ?array $empresas = null,
    ) {}
}
