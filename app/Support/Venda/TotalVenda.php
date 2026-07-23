<?php

declare(strict_types=1);

namespace App\Support\Venda;

/**
 * Calculo do total de uma venda de produto a partir dos itens crus (carrinho).
 *
 * Fonte unica da formula do subtotal por item — reusada pelo CriarVendaProdutoAction
 * (que persiste o subtotal por item) e pela validacao da soma dos recebimentos em
 * CriarVendaRequest, para os dois nunca divergirem.
 */
class TotalVenda
{
    /**
     * Subtotal de um item: valor_unitario * quantidade - desconto + acrescimo.
     *
     * @param  array<string, mixed>  $item
     */
    public static function deItem(array $item): float
    {
        $quantidade = (float) ($item['quantidade'] ?? 0);
        $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
        $desconto = (float) ($item['desconto'] ?? 0);
        $acrescimo = (float) ($item['acrescimo'] ?? 0);

        return ($valorUnitario * $quantidade) - $desconto + $acrescimo;
    }

    /**
     * Total da venda = soma dos subtotais dos itens.
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    public static function deItens(array $itens): float
    {
        return round(array_sum(array_map(self::deItem(...), $itens)), 2);
    }
}
