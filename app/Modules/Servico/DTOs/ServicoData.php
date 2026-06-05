<?php

namespace App\Modules\Servico\DTOs;

use App\Enums\TipoServico;
use Spatie\LaravelData\Data;

class ServicoData extends Data
{
    public function __construct(
        public string $nome,
        public int $duracao,
        public float $valor,
        public TipoServico $tipo = TipoServico::Unico,
        public ?int $qtd_etapas = null,
        public ?string $descricao = null,
    ) {}
}
