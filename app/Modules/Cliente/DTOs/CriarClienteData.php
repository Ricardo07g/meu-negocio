<?php

namespace App\Modules\Cliente\DTOs;

use Spatie\LaravelData\Data;

class CriarClienteData extends Data
{
    public function __construct(
        public string $nome,
        public ?string $telefone = null,
        public ?bool $telefone_whatsapp = false,
        public ?string $email = null,
        public ?string $data_nascimento = null,
        public ?string $cpf = null,
        public ?string $sexo = null,
        public ?string $cep = null,
        public ?string $estado = null,
        public ?string $cidade = null,
        public ?string $bairro = null,
        public ?string $logradouro = null,
        public ?string $numero = null,
        public ?string $complemento = null,
        public ?string $observacoes = null,
    ) {}
}
