<?php

namespace App\Modules\Despesa\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class AtualizarDespesaData extends Data
{
    public function __construct(
        public string $nome,
        public float $valor,
        public Carbon $data,
    ) {}
}
