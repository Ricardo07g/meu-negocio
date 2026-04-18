<?php

namespace App\Modules\Despesa\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class DespesaData extends Data
{
    public function __construct(
        public string $nome,
        public float $valor,
        public Carbon $data,
    ) {}
}
