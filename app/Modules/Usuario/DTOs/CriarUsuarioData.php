<?php

namespace App\Modules\Usuario\DTOs;

use Spatie\LaravelData\Data;

class CriarUsuarioData extends Data
{
    public function __construct(
        public string $nome,
        public string $email,
        public string $password,
        public ?int $empresa_id = null,
        public string $papel = 'Visualizador',
        public ?bool $atende = null,
    ) {}
}
