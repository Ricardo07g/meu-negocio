<?php

declare(strict_types=1);

namespace App\Modules\Venda\Actions;

use App\Enums\{CondicaoPagamento, FormaPagamento, FormaRecebimentoPrazo, StatusVendaProduto};
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Pagamento\Actions\CriarPagamentoComParcelasAction;
use App\Modules\Pagamento\DTOs\CriarPagamentoData;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\{VendaProduto, VendaProdutoItem};
use Carbon\Carbon;

class CriarVendaProdutoAction
{
    public function __construct(
        private CriarPagamentoComParcelasAction $criarPagamento,
    ) {}

    /**
     * Cria a VendaProduto + itens (baixando estoque) + Pagamento com parcelas.
     * A baixa a vista e feita posteriormente pelo VendaService (precisa do CaixaService).
     *
     * @return array{venda: VendaProduto, pagamento: Pagamento}
     */
    public function executar(
        ?int $cliente_id,
        array $itens,
        CondicaoPagamento $condicao,
        Carbon $mesReferencia,
        ?FormaPagamento $formaAvista = null,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?string $data = null,
        ?string $observacao = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): array {
        $subtotal = 0;

        foreach ($itens as &$item) {
            $produto = Produto::findOrFail($item['produto_id']);
            $item['descricao'] = $produto->nome;
            $item['valor_unitario'] = $item['valor_unitario'] ?? $produto->valor_venda;
            $item['desconto'] = $item['desconto'] ?? 0;
            $item['acrescimo'] = $item['acrescimo'] ?? 0;
            $item['subtotal'] = ($item['valor_unitario'] * $item['quantidade']) - $item['desconto'] + $item['acrescimo'];
            $subtotal += $item['subtotal'];
        }
        unset($item);

        $venda = VendaProduto::create([
            'cliente_id' => $cliente_id ?: null,
            'usuario_id' => auth()->id(),
            'data' => $data ?? now()->toDateString(),
            'subtotal' => $subtotal,
            'valor_total' => $subtotal,
            'status' => StatusVendaProduto::Ativa,
            'observacao' => $observacao,
        ]);

        foreach ($itens as $item) {
            VendaProdutoItem::create([
                'venda_produto_id' => $venda->id,
                'produto_id' => $item['produto_id'],
                'descricao' => $item['descricao'],
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'],
                'desconto' => $item['desconto'],
                'acrescimo' => $item['acrescimo'],
                'subtotal' => $item['subtotal'],
            ]);

            Produto::find($item['produto_id'])->decrement('quantidade', $item['quantidade']);

            MovimentoEstoque::create([
                'produto_id' => $item['produto_id'],
                'tipo' => 'saida',
                'quantidade' => $item['quantidade'],
            ]);
        }

        $pagamento = $this->criarPagamento->executar(new CriarPagamentoData(
            valor_total: (float) $venda->valor_total,
            condicao_pagamento: $condicao,
            mes_referencia: $mesReferencia,
            cliente_id: $cliente_id,
            venda_produto_id: $venda->id,
            numero_parcelas: $numeroParcelas,
            primeiro_vencimento: $primeiroVencimento ?? now(),
            forma_pagamento_avista: $formaAvista,
            forma_recebimento_prazo: $formaRecebimentoPrazo,
            parcelas_personalizadas: $parcelasPersonalizadas,
        ));

        return ['venda' => $venda, 'pagamento' => $pagamento];
    }
}
