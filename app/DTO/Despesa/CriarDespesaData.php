<?php

namespace App\DTO\Despesa;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CriarDespesaData extends Data
{
    public function __construct(
        public string $nome,
        public float $valor,
        public Carbon $data,
    ) {}
}
