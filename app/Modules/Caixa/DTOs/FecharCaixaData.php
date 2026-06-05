<?php

declare(strict_types=1);

namespace App\Modules\Caixa\DTOs;

use Spatie\LaravelData\Data;

class FecharCaixaData extends Data
{
    public function __construct(
        public float $saldo_fechamento,
        public ?string $observacao,
    ) {}
}
