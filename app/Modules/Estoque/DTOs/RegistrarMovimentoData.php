<?php

declare(strict_types=1);

namespace App\Modules\Estoque\DTOs;

use App\Enums\TipoMovimentoEstoque;
use Spatie\LaravelData\Data;

class RegistrarMovimentoData extends Data
{
    public function __construct(
        public int $produto_id,
        public TipoMovimentoEstoque $tipo,
        public int $quantidade,
    ) {}
}
