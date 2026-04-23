<?php

namespace App\Modules\Pagamento\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class RenegociarParcelaData extends Data
{
    public function __construct(
        public Carbon $data_vencimento,
        public float $valor,
        public ?string $observacao = null,
    ) {}
}
