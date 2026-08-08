<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Services;

use App\Enums\{CondicaoPagamento, TipoMovimentacaoDia};
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento};
use App\Modules\Conta\Models\Lancamento;
use Illuminate\Support\{Carbon, Collection};

/**
 * Timeline única do dia da loja (leitura): TUDO que movimentou dinheiro, venha de
 * qual forma vier — venda recebida, título a prazo baixado, despesa paga, estorno,
 * sangria e reforço.
 *
 * Por que não ler `caixa->lancamentos`: só dinheiro em espécie gera `Lancamento`
 * (ADR-0011), então uma venda no cartão não apareceria — e era isso que fazia a tela
 * do Caixa parecer quebrada ("recebi no cartão e não vejo movimento nenhum"). Esse
 * razão continua existindo e tem casa própria: `/contas/{conta-caixa}/extrato`.
 *
 * PARTIÇÃO DISJUNTA (o jeito de errar aqui é contar em dobro): um recebimento em
 * dinheiro existe DUAS vezes no banco — como `BaixaPagamento` e como `Lancamento` de
 * crédito. Por isso as vendas/despesas/estornos vêm do eixo das BAIXAS, e do eixo dos
 * LANÇAMENTOS entram apenas `sangria`/`reforco`, que são os únicos eventos nativos da
 * gaveta e não têm baixa correspondente. `categoria` `movimento`/`estorno` fica FORA.
 *
 * O saldo da gaveta não muda de fonte: segue vindo só de `Caixa::saldoCalculado()`.
 * A flag `tocaGaveta` da linha é o que torna essa conta visível na tela.
 *
 * Tenancy pela EmpresaTrait, como o ResumoDiaService (a tela do Caixa já resolveu a
 * empresa única antes de chamar).
 *
 * @phpstan-type LinhaMovimentacao array{
 *     momento: Carbon,
 *     tipo: TipoMovimentacaoDia,
 *     titulo: string,
 *     detalhe: string|null,
 *     forma: string|null,
 *     conta: string|null,
 *     tocaGaveta: bool,
 *     valor: float,
 *     estornada: bool,
 *     url: string|null
 * }
 */
class MovimentacaoDiaService
{
    /**
     * @return array{
     *     linhas: list<LinhaMovimentacao>,
     *     totalEntradas: float,
     *     totalSaidas: float,
     *     resultado: float,
     *     qtdRecebimentos: int,
     *     qtdDespesas: int
     * }
     */
    public function doDia(string $dia): array
    {
        $linhas = array_merge(
            $this->recebimentos($dia),
            $this->estornos($dia),
            $this->despesasPagas($dia),
            $this->movimentosDaGaveta($dia),
        );

        usort($linhas, fn (array $a, array $b): int => $b['momento'] <=> $a['momento']);

        // Sangria/reforço não entram no resultado: são dinheiro trocando de lugar
        // (gaveta <-> bolso/banco), não receita nem despesa do negócio.
        $noResultado = collect($linhas)->filter(fn (array $l): bool => $l['tipo']->contaNoResultado());

        $totalEntradas = round((float) $noResultado->filter(fn (array $l): bool => $l['tipo']->ehEntrada())->sum('valor'), 2);
        $totalSaidas = round((float) $noResultado->reject(fn (array $l): bool => $l['tipo']->ehEntrada())->sum('valor'), 2);

        return [
            'linhas' => $linhas,
            'totalEntradas' => $totalEntradas,
            'totalSaidas' => $totalSaidas,
            'resultado' => round($totalEntradas - $totalSaidas, 2),
            'qtdRecebimentos' => count(array_filter(
                $linhas,
                fn (array $l): bool => in_array($l['tipo'], [TipoMovimentacaoDia::Venda, TipoMovimentacaoDia::Recebimento], true),
            )),
            'qtdDespesas' => count(array_filter($linhas, fn (array $l): bool => $l['tipo'] === TipoMovimentacaoDia::Despesa)),
        ];
    }

    /**
     * O que o cliente pagou no dia. Inclui as baixas já estornadas (marcadas com
     * `estornada`) — mesma leitura do ResumoDiaService: recebido e estornado são
     * dois eventos, e ambos aconteceram. Some a venda e some o cancelamento, em
     * linhas separadas, em vez de fazer a venda desaparecer do dia.
     *
     * @return list<LinhaMovimentacao>
     */
    private function recebimentos(string $dia): array
    {
        return $this->baixasDoDia($dia)->map(function (BaixaPagamento $baixa): array {
            $pagamento = $baixa->parcela->pagamento;

            return $this->linha(
                momento: $baixa->data,
                // Venda a prazo baixada num dia posterior não é "uma venda de hoje":
                // hoje só entrou dinheiro de um título que já existia.
                tipo: $pagamento->condicao_pagamento === CondicaoPagamento::AVista
                    ? TipoMovimentacaoDia::Venda
                    : TipoMovimentacaoDia::Recebimento,
                titulo: $pagamento->cliente->nome ?? 'Venda no balcão',
                detalhe: $this->origemDoPagamento($baixa),
                forma: $baixa->rotuloForma(),
                conta: $baixa->conta?->nome,
                tocaGaveta: $this->tocaGaveta($baixa),
                valor: $baixa->valorTotal(),
                estornada: $baixa->estornado_em !== null,
                url: $this->urlDaVenda($baixa),
            );
        })->values()->all();
    }

    /**
     * Vendas canceladas no dia. Valuadas pelo BRUTO da própria baixa, para netar
     * exato contra o recebimento (ADR-0011).
     *
     * @return list<LinhaMovimentacao>
     */
    private function estornos(string $dia): array
    {
        $baixas = BaixaPagamento::query()
            ->whereBetween('estornado_em', [$dia.' 00:00:00', $dia.' 23:59:59'])
            ->comOrigem()
            ->with($this->relacoesDoRecebimento())
            ->get();

        return $baixas->map(function (BaixaPagamento $baixa): array {
            $pagamento = $baixa->parcela->pagamento;
            $origem = $this->origemDoPagamento($baixa);

            return $this->linha(
                // Nao-nulo por construcao: a query filtra por `estornado_em` no intervalo.
                momento: $baixa->estornado_em ?? $baixa->data,
                tipo: TipoMovimentacaoDia::Estorno,
                titulo: $pagamento->cliente->nome ?? 'Venda no balcão',
                detalhe: 'Venda cancelada'.($origem !== null ? " · {$origem}" : ''),
                forma: $baixa->rotuloForma(),
                conta: $baixa->conta?->nome,
                tocaGaveta: $this->tocaGaveta($baixa),
                valor: $baixa->valorTotal(),
                url: $this->urlDaVenda($baixa),
            );
        })->values()->all();
    }

    /**
     * Contas a pagar quitadas no dia — a metade do dia que a tela do Caixa não
     * mostrava (em dinheiro virava um débito genérico "Saída"; no pix, nada).
     *
     * @return list<LinhaMovimentacao>
     */
    private function despesasPagas(string $dia): array
    {
        // withTrashed na espinha: mesma razao do recebimento — Despesa e ParcelaDespesa
        // usam SoftDeletes, e sem isso uma despesa excluida deixaria a linha sem rotulo.
        $baixas = BaixaDespesa::query()
            ->whereBetween('data', [$dia.' 00:00:00', $dia.' 23:59:59'])
            ->with([
                'parcela' => fn ($q) => $q->withTrashed(),
                'parcela.despesa' => fn ($q) => $q->withTrashed(),
                'parcela.despesa.categoria',
                'conta',
            ])
            ->get();

        return $baixas->map(function (BaixaDespesa $baixa): array {
            $parcela = $baixa->parcela;
            $despesa = $parcela->despesa;

            // CategoriaDespesa e rotulada por `descricao` (nao `nome`, como a Despesa).
            $detalhe = collect([
                $despesa->fornecedor_nome,
                $despesa->categoria->descricao ?? null,
                $parcela->total > 1 ? "Parcela {$parcela->numero}/{$parcela->total}" : null,
            ])->filter()->implode(' · ');

            return $this->linha(
                momento: $baixa->data,
                tipo: TipoMovimentacaoDia::Despesa,
                titulo: $despesa->nome,
                detalhe: $detalhe !== '' ? $detalhe : null,
                forma: $baixa->forma_pagamento_nome,
                conta: $baixa->conta?->nome,
                tocaGaveta: $this->tocaGaveta($baixa),
                valor: $baixa->valorTotal(),
            );
        })->values()->all();
    }

    /**
     * Sangria e reforço: os únicos eventos que nascem no razão da gaveta sem uma
     * baixa por trás. Todo o resto do `Lancamento` (categorias `movimento` e
     * `estorno`) é espelho das baixas acima e ficaria duplicado.
     *
     * @return list<LinhaMovimentacao>
     */
    private function movimentosDaGaveta(string $dia): array
    {
        $lancamentos = Lancamento::query()
            ->whereDate('data', $dia)
            ->whereIn('categoria', ['sangria', 'reforco'])
            ->with('conta')
            ->get();

        return $lancamentos->map(fn (Lancamento $lancamento): array => $this->linha(
            momento: $lancamento->created_at ?? $lancamento->data,
            tipo: $lancamento->categoria === 'sangria'
                ? TipoMovimentacaoDia::Sangria
                : TipoMovimentacaoDia::Reforco,
            titulo: $lancamento->descricao,
            detalhe: null,
            forma: null,
            conta: $lancamento->conta->nome,
            tocaGaveta: true,
            valor: (float) $lancamento->valor,
        ))->values()->all();
    }

    /**
     * Fabrica da linha da timeline: um lugar so define o formato que as quatro fontes
     * tem de produzir (e o que a view consome).
     *
     * @return LinhaMovimentacao
     */
    private function linha(
        Carbon $momento,
        TipoMovimentacaoDia $tipo,
        string $titulo,
        ?string $detalhe,
        ?string $forma,
        ?string $conta,
        bool $tocaGaveta,
        float $valor,
        bool $estornada = false,
        ?string $url = null,
    ): array {
        return [
            'momento' => $momento,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'detalhe' => $detalhe,
            'forma' => $forma,
            'conta' => $conta,
            'tocaGaveta' => $tocaGaveta,
            'valor' => $valor,
            'estornada' => $estornada,
            'url' => $url,
        ];
    }

    /**
     * @return Collection<int, BaixaPagamento>
     */
    private function baixasDoDia(string $dia): Collection
    {
        return BaixaPagamento::query()
            ->whereBetween('data', [$dia.' 00:00:00', $dia.' 23:59:59'])
            ->comOrigem()
            ->with($this->relacoesDoRecebimento())
            ->get();
    }

    /**
     * Eager loading do recebimento. Sem isso é N+1 por linha da timeline: cada linha
     * toca parcela -> pagamento -> cliente e a origem (serviço/pacote).
     *
     * A espinha `parcela -> pagamento -> cliente` com `withTrashed()` vem do scope
     * `BaixaPagamento::comOrigem()` — ponto único, porque a mesma pegadinha derruba
     * o extrato da conta e a exportação: os dois usam SoftDeletes e a baixa NÃO,
     * então uma venda cancelada deixaria a linha sem cliente. Aqui só acrescentamos
     * o que é específico da timeline (a origem serviço/pacote e a conta).
     *
     * @return array<array-key, \Closure|string>
     */
    private function relacoesDoRecebimento(): array
    {
        return [
            'parcela.pagamento.agendamento.servico',
            'parcela.pagamento.vendaEtapas.servico',
            'conta',
        ];
    }

    /**
     * De onde veio o dinheiro: venda de produtos, serviço, pacote de etapas ou um
     * título avulso — mais o número da parcela quando o título é parcelado.
     *
     * A venda de produtos é rotulada pela FK (`venda_produto_id`), não pela relação:
     * só o id é exibido, e assim o rótulo sobrevive à venda excluída.
     */
    private function origemDoPagamento(BaixaPagamento $baixa): ?string
    {
        $parcela = $baixa->parcela;
        $pagamento = $parcela->pagamento;

        $origem = match (true) {
            $pagamento->venda_produto_id !== null => 'Venda de produtos #'.$pagamento->venda_produto_id,
            $pagamento->agendamento_id !== null => 'Serviço · '.($pagamento->agendamento->servico->nome ?? '—'),
            $pagamento->venda_etapas_id !== null => 'Pacote · '.($pagamento->vendaEtapas->servico->nome ?? '—'),
            default => $pagamento->descricao,
        };

        $partes = collect([
            $origem,
            $parcela->total > 1 ? "Parcela {$parcela->numero}/{$parcela->total}" : null,
        ])->filter();

        return $partes->isNotEmpty() ? $partes->implode(' · ') : null;
    }

    /** Link para a origem — só onde existe tela de verdade (a venda). */
    private function urlDaVenda(BaixaPagamento $baixa): ?string
    {
        $pagamento = $baixa->parcela->pagamento;

        return match (true) {
            $pagamento->venda_produto_id !== null => route('vendas.show', ['produto', $pagamento->venda_produto_id]),
            $pagamento->agendamento_id !== null => route('vendas.show', ['unico', $pagamento->agendamento_id]),
            $pagamento->venda_etapas_id !== null => route('vendas.show', ['etapas', $pagamento->venda_etapas_id]),
            default => null,
        };
    }

    /**
     * A linha mexe no saldo da gaveta? ⟺ a conta da baixa é do tipo Caixa. O
     * fallback por `caixa_id` cobre baixas antigas, gravadas antes de `conta_id`
     * existir, que só apontavam para a sessão de caixa.
     */
    private function tocaGaveta(BaixaPagamento|BaixaDespesa $baixa): bool
    {
        return $baixa->conta?->tipo->ehCaixa() ?? ($baixa->caixa_id !== null);
    }
}
