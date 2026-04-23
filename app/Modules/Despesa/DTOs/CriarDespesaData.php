<?php

namespace App\Modules\Despesa\DTOs;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CriarDespesaData extends Data
{
    public function __construct(
        public string $nome,
        public float $valor_total,
        public CondicaoPagamento $condicao_pagamento,
        public Carbon $mes_referencia,
        public Carbon $data_emissao,
        public Carbon $primeiro_vencimento,
        public ?int $categoria_despesa_id = null,
        public ?string $fornecedor_nome = null,
        public ?string $documento = null,
        public ?string $observacoes = null,
        public ?int $numero_parcelas = null,
        public ?FormaPagamento $forma_pagamento_avista = null,
        public ?FormaRecebimentoPrazo $forma_recebimento_prazo = null,
        public ?array $parcelas_personalizadas = null,
    ) {}
}
