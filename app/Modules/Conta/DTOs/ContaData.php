<?php

declare(strict_types=1);

namespace App\Modules\Conta\DTOs;

use App\Enums\TipoConta;
use Spatie\LaravelData\Data;

class ContaData extends Data
{
    public function __construct(
        public string $nome,
        public TipoConta $tipo,
        public float $saldo_inicial = 0,
        public bool $ativo = true,
        public bool $eh_caixa_padrao = false,
        public bool $eh_destino_recebivel_padrao = false,
        public ?string $instituicao = null,
        public ?string $agencia = null,
        public ?string $numero = null,
    ) {}
}
