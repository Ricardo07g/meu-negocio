<?php

declare(strict_types=1);

namespace App\Modules\Venda\DTOs;

use Spatie\LaravelData\Data;

class VenderEtapasData extends Data
{
    public function __construct(
        public int $cliente_id,
        public int $servico_id,
        public int $atendente_id,
        public float $valor_total,
        public string $horario,
        /** @var array<string> Array de datas no formato Y-m-d */
        public array $datas,
        /** @var array<string>|null Array de horários no formato H:i (um por sessão) */
        public ?array $horarios = null,
    ) {}
}
