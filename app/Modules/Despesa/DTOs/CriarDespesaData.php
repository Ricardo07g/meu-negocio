<?php

declare(strict_types=1);

namespace App\Modules\Despesa\DTOs;

use App\Enums\{CondicaoPagamento, FormaRecebimentoPrazo};
use App\Modules\FormaPagamento\Models\FormaPagamento;
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
