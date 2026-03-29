<?php

namespace App\DTO\Agendamento;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CriarAgendamentoData extends Data
{
    public function __construct(
        public int $cliente_id,
        public int $servico_id,
        public int $profissional_id,
        public Carbon $inicio,
        public ?Carbon $fim = null,
    ) {}
}
