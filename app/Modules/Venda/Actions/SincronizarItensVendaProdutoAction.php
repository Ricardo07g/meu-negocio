<?php

declare(strict_types=1);

namespace App\Modules\Venda\Actions;

use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\{VendaProduto, VendaProdutoItem};

class SincronizarItensVendaProdutoAction
{
    /**
     * Sincroniza os itens de uma VendaProduto com a lista recebida:
     * - itens existentes sao atualizados (e ajustam estoque pela diferenca)
     * - itens novos sao criados (baixa estoque)
     * - itens removidos tem o estoque devolvido
     */
    public function executar(VendaProduto $venda, array $novosItens): void
    {
        $antigosPorId = $venda->itens->keyBy('id');
        $idsManipulados = [];

        foreach ($novosItens as $entrada) {
            $produtoId = (int) ($entrada['produto_id'] ?? 0);
            $quantidade = (int) ($entrada['quantidade'] ?? 0);
            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }

            $produto = Produto::findOrFail($produtoId);
            $valorUnitario = (float) ($entrada['valor_unitario'] ?? $produto->valor_venda);
            $descontoItem = (float) ($entrada['desconto'] ?? 0);
            $acrescimoItem = (float) ($entrada['acrescimo'] ?? 0);
            $subtotalItem = ($valorUnitario * $quantidade) - $descontoItem + $acrescimoItem;

            $itemAntigoId = isset($entrada['id']) ? (int) $entrada['id'] : null;
            $itemAntigo = $itemAntigoId ? ($antigosPorId[$itemAntigoId] ?? null) : null;

            if ($itemAntigo) {
                $idsManipulados[] = $itemAntigo->id;
                $diff = $quantidade - $itemAntigo->quantidade;
                if ($diff !== 0) {
                    $produto->decrement('quantidade', $diff);
                    MovimentoEstoque::create([
                        'produto_id' => $produto->id,
                        'tipo' => $diff > 0 ? 'saida' : 'entrada',
                        'quantidade' => abs($diff),
                    ]);
                }
                $itemAntigo->update([
                    'produto_id' => $produto->id,
                    'descricao' => $produto->nome,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'desconto' => $descontoItem,
                    'acrescimo' => $acrescimoItem,
                    'subtotal' => $subtotalItem,
                ]);
            } else {
                $produto->decrement('quantidade', $quantidade);
                MovimentoEstoque::create([
                    'produto_id' => $produto->id,
                    'tipo' => 'saida',
                    'quantidade' => $quantidade,
                ]);
                $novo = VendaProdutoItem::create([
                    'venda_produto_id' => $venda->id,
                    'produto_id' => $produto->id,
                    'descricao' => $produto->nome,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'desconto' => $descontoItem,
                    'acrescimo' => $acrescimoItem,
                    'subtotal' => $subtotalItem,
                ]);
                $idsManipulados[] = $novo->id;
            }
        }

        foreach ($antigosPorId as $id => $itemAntigo) {
            if (in_array($id, $idsManipulados, true)) {
                continue;
            }
            Produto::find($itemAntigo->produto_id)?->increment('quantidade', $itemAntigo->quantidade);
            MovimentoEstoque::create([
                'produto_id' => $itemAntigo->produto_id,
                'tipo' => 'entrada',
                'quantidade' => $itemAntigo->quantidade,
            ]);
            $itemAntigo->delete();
        }
    }
}
