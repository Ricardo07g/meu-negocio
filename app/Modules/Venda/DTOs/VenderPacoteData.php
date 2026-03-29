<?php

namespace App\Modules\Venda\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class VenderPacoteData extends Data
{
    public function __construct(
        public int $cliente_id,
        public int $servico_id,
        public int $profissional_id,
        public float $valor_total,
        public string $horario,
        /** @var array<string> Array de datas no formato Y-m-d */
        public array $datas,
    ) {}
}
