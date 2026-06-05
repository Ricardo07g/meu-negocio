<?php

declare(strict_types=1);

namespace App\Modules\Caixa\DTOs;

use Spatie\LaravelData\Data;

class ReabrirCaixaData extends Data
{
    public function __construct(
        public string $motivo,
    ) {}
}
