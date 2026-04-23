<?php

namespace App\Modules\Pagamento\DTOs;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * Dados para criar um Pagamento (título) com suas parcelas.
 *
 * - AVista       : n_parcelas ignorado (sempre 1), forma_pagamento obrigatória.
 * - APrazo       : n_parcelas entre 2-24, vencimento inicial em primeiro_vencimento,
 *                  forma_pagamento obrigatória (aplicada a todas as parcelas como forma prevista).
 *
 * Quando parcelas_personalizadas vem preenchido (do preview editado pelo usuario),
 * o action usa esses valores literalmente em vez de calcular. Cada item do array:
 *   ['numero' => int, 'total' => int, 'valor' => float,
 *    'data_vencimento' => Carbon, 'mes_referencia' => Carbon]
 */
class CriarPagamentoData extends Data
{
    public function __construct(
        public float $valor_total,
        public CondicaoPagamento $condicao_pagamento,
        public Carbon $mes_referencia,
        public ?int $cliente_id = null,
        public ?int $agendamento_id = null,
        public ?int $venda_pacote_id = null,
        public ?int $venda_produto_id = null,
        public ?int $numero_parcelas = null,
        public ?Carbon $primeiro_vencimento = null,
        public ?FormaPagamento $forma_pagamento_avista = null,
        public ?FormaRecebimentoPrazo $forma_recebimento_prazo = null,
        public ?string $descricao = null,
        public ?array $parcelas_personalizadas = null,
    ) {}
}
