<?php

declare(strict_types=1);

namespace App\Modules\Venda\DTOs;

use App\Modules\FormaPagamento\Models\FormaPagamento;
use Spatie\LaravelData\Data;

/**
 * Uma linha de recebimento de uma venda (split de formas).
 *
 * A venda pode ter N recebimentos de formas distintas (parte pix, parte dinheiro,
 * parte cartao), desde que a soma dos valores bata com o total da venda. Cada
 * recebimento vira uma Baixa na parcela unica (venda a vista), roteada pela sua
 * forma. `parcelas_cartao` so faz sentido em cartao parcelavel (informativo).
 */
class RecebimentoData extends Data
{
    public function __construct(
        public FormaPagamento $forma,
        public float $valor,
        public ?int $parcelas_cartao = null,
    ) {}
}
