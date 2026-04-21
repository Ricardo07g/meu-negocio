<?php

namespace App\Modules\Despesa\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class DespesaData extends Data
{
    public function __construct(
        public string $nome,
        public float $valor,
        public Carbon $data_emissao,
        public Carbon $data_vencimento,
        public Carbon $competencia,
        public ?int $categoria_despesa_id = null,
        public ?string $fornecedor_nome = null,
        public ?string $documento = null,
        public ?string $observacoes = null,
        public bool $parcelar = false,
        public ?int $numero_parcelas = null,
    ) {}
}
