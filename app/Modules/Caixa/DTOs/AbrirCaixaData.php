<?php

namespace App\Modules\Caixa\DTOs;

use Spatie\LaravelData\Data;

class AbrirCaixaData extends Data
{
    public function __construct(
        public float $saldo_abertura,
        public ?string $observacao,
    ) {}
}
