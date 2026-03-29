<?php

namespace App\Modules\Agenda\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class AtualizarAgendamentoData extends Data
{
    public function __construct(
        public ?int $cliente_id = null,
        public ?int $servico_id = null,
        public ?int $profissional_id = null,
        public ?Carbon $inicio = null,
        public ?Carbon $fim = null,
        public ?string $observacoes = null,
    ) {}
}
