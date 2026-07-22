<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\DTOs;

use App\Enums\TipoFormaPagamento;
use Spatie\LaravelData\Data;

class FormaPagamentoData extends Data
{
    public function __construct(
        public string $nome,
        public TipoFormaPagamento $tipo,
        public bool $ativo = true,
        public bool $gera_recebivel = false,
        public int $dias_liquidacao = 0,
        public float $taxa_percentual = 0,
        public bool $permite_parcelas = false,
        public ?int $max_parcelas = null,
        public bool $antecipacao_automatica = false,
        public float $taxa_antecipacao_mensal = 0,
        public ?int $conta_destino_id = null,
    ) {}
}
