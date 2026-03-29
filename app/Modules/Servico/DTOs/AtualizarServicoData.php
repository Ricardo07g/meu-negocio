<?php

namespace App\Modules\Servico\DTOs;

use App\Enums\TipoServico;
use Spatie\LaravelData\Data;

class AtualizarServicoData extends Data
{
    public function __construct(
        public string $nome,
        public int $duracao,
        public float $valor,
        public TipoServico $tipo = TipoServico::Avulso,
        public ?int $qtd_sessoes = null,
        public ?string $descricao = null,
    ) {}
}
