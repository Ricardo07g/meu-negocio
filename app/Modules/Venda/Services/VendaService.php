<?php

declare(strict_types=1);

namespace App\Modules\Venda\Services;

use App\Enums\{CondicaoPagamento, FormaRecebimentoPrazo, StatusParcela, StatusVendaEtapas, StatusVendaProduto};
use App\Modules\Agenda\Actions\{CancelarAgendamentoAction, CriarAgendamentoAction};
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Pagamento\Actions\CriarPagamentoComParcelasAction;
use App\Modules\Pagamento\DTOs\CriarPagamentoData;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Venda\Actions\{CriarVendaProdutoAction, VenderEtapasAction};
use App\Modules\Venda\DTOs\{RecebimentoData, VenderEtapasData};
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendaService
{
    public function __construct(
        private CriarAgendamentoAction $criarAgendamento,
        private VenderEtapasAction $venderEtapas,
        private CancelarAgendamentoAction $cancelarAgendamento,
        private CaixaService $caixaService,
        private CriarPagamentoComParcelasAction $criarPagamento,
        private CriarVendaProdutoAction $criarVendaProdutoAction,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $tipo = $filtros['tipo'] ?? 'todos';
        [$dataInicio, $dataFim] = $this->resolverPeriodo($filtros);

        $etapas = collect();
        $unicos = collect();
        $produtos = collect();

        if ($tipo !== 'produto') {
            $etapasQuery = VendaEtapas::with(['cliente', 'servico', 'atendente', 'agendamentos', 'pagamento.parcelas'])
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($etapasQuery, $filtros, $dataInicio, $dataFim, 'etapas');
            $this->aplicarBuscaEtapas($etapasQuery, $filtros['q'] ?? null);
            $this->aplicarStatusEtapas($etapasQuery, $filtros['status_venda'] ?? null);
            $etapas = $etapasQuery->get()->map(fn ($p) => $this->mapearEtapas($p));

            $unicosQuery = Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento.parcelas'])
                ->whereNull('venda_etapas_id')
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($unicosQuery, $filtros, $dataInicio, $dataFim, 'unico');
            $this->aplicarBuscaUnico($unicosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusUnico($unicosQuery, $filtros['status_venda'] ?? null);
            $this->aplicarValorUnico($unicosQuery, $filtros);
            $unicos = $unicosQuery->get()->map(fn ($a) => $this->mapearUnico($a));
        }

        if ($tipo !== 'servico') {
            $produtosQuery = VendaProduto::with(['cliente', 'usuario', 'itens.produto.arquivoPrincipal', 'pagamento.parcelas'])
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($produtosQuery, $filtros, $dataInicio, $dataFim, 'produto');
            $this->aplicarBuscaProduto($produtosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusProduto($produtosQuery, $filtros['status_venda'] ?? null);
            $produtos = $produtosQuery->get()->map(fn ($vp) => $this->mapearProduto($vp));
        }

        $vendas = collect($etapas)->merge($unicos)->merge($produtos)->sortByDesc('data')->values();

        $page = (int) (request()->query('page', 1));
        $slice = $vendas->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $vendas->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function resolverPeriodo(array $filtros): array
    {
        $preset = $filtros['periodo_preset'] ?? null;
        $hoje = now()->startOfDay();

        $inicio = null;
        $fim = null;

        match ($preset) {
            'hoje' => [$inicio, $fim] = [$hoje, now()->endOfDay()],
            'ontem' => [$inicio, $fim] = [$hoje->copy()->subDay(), $hoje->copy()->subDay()->endOfDay()],
            'esta_semana' => [$inicio, $fim] = [now()->startOfWeek(), now()->endOfWeek()],
            'este_mes' => [$inicio, $fim] = [now()->startOfMonth(), now()->endOfMonth()],
            'mes_passado' => [$inicio, $fim] = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'ultimos_30_dias' => [$inicio, $fim] = [$hoje->copy()->subDays(30), now()->endOfDay()],
            'ultimos_90_dias' => [$inicio, $fim] = [$hoje->copy()->subDays(90), now()->endOfDay()],
            default => null,
        };

        if (! empty($filtros['data_inicio'])) {
            $inicio = Carbon::parse($filtros['data_inicio'])->startOfDay();
        }
        if (! empty($filtros['data_fim'])) {
            $fim = Carbon::parse($filtros['data_fim'])->endOfDay();
        }

        return [$inicio, $fim];
    }

    private function aplicarFiltrosComuns($query, array $filtros, $dataInicio, $dataFim, string $origem): void
    {
        if ($dataInicio && $dataFim) {
            $query->whereBetween('created_at', [$dataInicio, $dataFim]);
        } elseif ($dataInicio) {
            $query->where('created_at', '>=', $dataInicio);
        } elseif ($dataFim) {
            $query->where('created_at', '<=', $dataFim);
        }

        if (! empty($filtros['situacao_pagamento'])) {
            $situacao = $filtros['situacao_pagamento'];
            $hoje = now()->toDateString();
            $query->whereHas('pagamento', function ($q) use ($situacao, $hoje) {
                match ($situacao) {
                    'pago' => $q->where('status', 'pago'),
                    'pendente' => $q->whereIn('status', ['pendente', 'parcial'])
                        ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                            ->whereDate('data_vencimento', '>=', $hoje)),
                    'vencido' => $q->whereIn('status', ['pendente', 'parcial'])
                        ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                            ->whereDate('data_vencimento', '<', $hoje)),
                    'estornado' => $q->where('status', 'estornado'),
                    default => null,
                };
            });
        }

        if (! empty($filtros['forma_pagamento'])) {
            $forma = $filtros['forma_pagamento'];
            $query->whereHas('pagamento', function ($q) use ($forma) {
                if ($forma === 'a_prazo') {
                    $q->where('condicao_pagamento', CondicaoPagamento::APrazo->value);
                } elseif ($forma === 'a_vista') {
                    $q->where('condicao_pagamento', CondicaoPagamento::AVista->value);
                } else {
                    $q->whereHas('parcelas', fn ($p) => $p->where('forma_pagamento_id', (int) $forma));
                }
            });
        }

        if (! empty($filtros['atendente_id'])) {
            $atendenteId = (int) $filtros['atendente_id'];
            if ($origem === 'produto') {
                $query->where('usuario_id', $atendenteId);
            } else {
                $query->where('atendente_id', $atendenteId);
            }
        }

        if ($origem !== 'unico') {
            if (! empty($filtros['valor_min'])) {
                $query->where('valor_total', '>=', (float) $filtros['valor_min']);
            }
            if (! empty($filtros['valor_max'])) {
                $query->where('valor_total', '<=', (float) $filtros['valor_max']);
            }
        }
    }

    private function aplicarValorUnico($query, array $filtros): void
    {
        if (! empty($filtros['valor_min'])) {
            $query->whereHas('servico', fn ($q) => $q->where('valor', '>=', (float) $filtros['valor_min']));
        }
        if (! empty($filtros['valor_max'])) {
            $query->whereHas('servico', fn ($q) => $q->where('valor', '<=', (float) $filtros['valor_max']));
        }
    }

    private function aplicarBuscaEtapas($query, ?string $q): void
    {
        if (! $q) {
            return;
        }
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('servico', fn ($r) => $r->where('nome', 'like', "%{$q}%"));
        });
    }

    private function aplicarBuscaUnico($query, ?string $q): void
    {
        if (! $q) {
            return;
        }
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('servico', fn ($r) => $r->where('nome', 'like', "%{$q}%"));
        });
    }

    private function aplicarBuscaProduto($query, ?string $q): void
    {
        if (! $q) {
            return;
        }
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('itens', fn ($r) => $r->where('descricao', 'like', "%{$q}%"));
        });
    }

    private function aplicarStatusEtapas($query, ?string $status): void
    {
        match ($status) {
            'em_andamento' => $query->where('status', 'ativo'),
            'concluido' => $query->where('status', 'concluido'),
            'cancelado' => $query->where('status', 'cancelado'),
            default => null,
        };
    }

    private function aplicarStatusUnico($query, ?string $status): void
    {
        match ($status) {
            'em_andamento' => $query->whereIn('status', ['agendado', 'confirmado']),
            'concluido' => $query->where('status', 'finalizado'),
            'cancelado' => $query->where('status', 'cancelado'),
            default => null,
        };
    }

    private function aplicarStatusProduto($query, ?string $status): void
    {
        match ($status) {
            'em_andamento' => $query->where('status', 'ativa'),
            'cancelado' => $query->where('status', 'cancelada'),
            'concluido' => $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    private function mapearEtapas(VendaEtapas $p): object
    {
        return (object) [
            'tipo' => 'etapas',
            'id' => $p->id,
            'cliente' => $p->cliente->nome,
            'servico' => $p->servico->nome,
            'info' => $p->qtd_etapas.' sessões',
            'valor' => $p->valor_total,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'cor' => $p->status->cor(),
            'data' => $p->created_at,
            'model' => $p,
        ];
    }

    private function mapearUnico(Agendamento $a): object
    {
        return (object) [
            'tipo' => 'unico',
            'id' => $a->id,
            'cliente' => $a->cliente->nome,
            'servico' => $a->servico->nome,
            'info' => $a->inicio->format('d/m/Y H:i'),
            'valor' => $a->servico->valor,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'cor' => $a->status->cor(),
            'data' => $a->created_at,
            'model' => $a,
        ];
    }

    private function mapearProduto(VendaProduto $vp): object
    {
        return (object) [
            'tipo' => 'produto',
            'id' => $vp->id,
            'cliente' => $vp->cliente->nome ?? '-',
            'servico' => $vp->itens->count().' produto(s)',
            'info' => $vp->itens->sum('quantidade').' un.',
            'valor' => $vp->valor_total,
            'status' => $vp->status->value,
            'status_label' => $vp->status->label(),
            'cor' => $vp->status->cor(),
            'data' => $vp->data ?? $vp->created_at,
            'model' => $vp,
        ];
    }

    /**
     * Carrega uma venda (por tipo) com as relações necessárias para a tela de
     * detalhes e devolve o mesmo objeto de linha usado na listagem.
     */
    public function detalhar(string $tipo, int $id): object
    {
        return match ($tipo) {
            'unico' => $this->mapearUnico(
                Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento.parcelas.baixas'])->findOrFail($id),
            ),
            'etapas' => $this->mapearEtapas(
                VendaEtapas::with(['cliente', 'servico', 'atendente', 'agendamentos.atendente', 'pagamento.parcelas.baixas'])->findOrFail($id),
            ),
            'produto' => $this->mapearProduto(
                VendaProduto::with(['cliente', 'usuario', 'itens.produto.arquivoPrincipal', 'pagamento.parcelas.baixas'])->findOrFail($id),
            ),
            default => abort(404, 'Tipo de venda inválido'),
        };
    }

    /**
     * Cria venda de serviço único + pagamento (título + parcelas).
     */
    public function criarUnico(
        AgendamentoData $data,
        CondicaoPagamento $condicao,
        Carbon $mesReferencia,
        array $recebimentos,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): Agendamento {
        return DB::transaction(function () use ($data, $condicao, $mesReferencia, $recebimentos, $numeroParcelas, $primeiroVencimento, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
            $agendamento = $this->criarAgendamento->executar($data);
            $servico = Servico::findOrFail($data->servico_id);

            $pagamento = $this->criarPagamento->executar(new CriarPagamentoData(
                valor_total: (float) $servico->valor,
                condicao_pagamento: $condicao,
                mes_referencia: $mesReferencia,
                cliente_id: $data->cliente_id,
                agendamento_id: $agendamento->id,
                numero_parcelas: $numeroParcelas,
                primeiro_vencimento: $primeiroVencimento ?? now(),
                forma_pagamento_avista: $recebimentos[0]->forma,
                forma_recebimento_prazo: $formaRecebimentoPrazo,
                parcelas_personalizadas: $parcelasPersonalizadas,
            ));

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $recebimentos);

            return $agendamento;
        });
    }

    /**
     * Cria venda de serviço em etapas + pagamento.
     */
    public function criarEtapas(
        VenderEtapasData $data,
        CondicaoPagamento $condicao,
        Carbon $mesReferencia,
        array $recebimentos,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): VendaEtapas {
        return DB::transaction(function () use ($data, $condicao, $mesReferencia, $recebimentos, $numeroParcelas, $primeiroVencimento, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
            $etapas = $this->venderEtapas->executar($data);

            $pagamento = $this->criarPagamento->executar(new CriarPagamentoData(
                valor_total: (float) $data->valor_total,
                condicao_pagamento: $condicao,
                mes_referencia: $mesReferencia,
                cliente_id: $data->cliente_id,
                venda_etapas_id: $etapas->id,
                numero_parcelas: $numeroParcelas,
                primeiro_vencimento: $primeiroVencimento ?? now(),
                forma_pagamento_avista: $recebimentos[0]->forma,
                forma_recebimento_prazo: $formaRecebimentoPrazo,
                parcelas_personalizadas: $parcelasPersonalizadas,
            ));

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $recebimentos);

            return $etapas;
        });
    }

    public function criarVendaProduto(
        ?int $cliente_id,
        array $itens,
        CondicaoPagamento $condicao,
        Carbon $mesReferencia,
        array $recebimentos,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?string $data = null,
        ?string $observacao = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): VendaProduto {
        return DB::transaction(function () use ($cliente_id, $itens, $condicao, $mesReferencia, $recebimentos, $numeroParcelas, $primeiroVencimento, $data, $observacao, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
            ['venda' => $venda, 'pagamento' => $pagamento] = $this->criarVendaProdutoAction->executar(
                cliente_id: $cliente_id,
                itens: $itens,
                condicao: $condicao,
                mesReferencia: $mesReferencia,
                formaAvista: $recebimentos[0]->forma,
                numeroParcelas: $numeroParcelas,
                primeiroVencimento: $primeiroVencimento,
                data: $data,
                observacao: $observacao,
                parcelasPersonalizadas: $parcelasPersonalizadas,
                formaRecebimentoPrazo: $formaRecebimentoPrazo,
            );

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $recebimentos);

            return $venda;
        });
    }

    /**
     * Se for venda à vista, aplica as baixas na parcela única — uma por
     * recebimento (split de formas: parte pix, parte dinheiro, parte cartão).
     * A soma dos recebimentos já foi validada == total da venda; cada baixa é
     * roteada pela sua forma (dinheiro exige caixa aberto e gera lançamento;
     * cartão/pix só registram a baixa). A prazo (crediário) não baixa aqui.
     *
     * @param  array<int, RecebimentoData>  $recebimentos
     */
    private function baixarAVistaSeAplicavel(Pagamento $pagamento, CondicaoPagamento $condicao, array $recebimentos): void
    {
        if ($condicao !== CondicaoPagamento::AVista) {
            return;
        }

        $parcela = $pagamento->parcelas->first();
        if (! $parcela) {
            return;
        }

        foreach ($recebimentos as $recebimento) {
            $this->caixaService->darBaixaParcelaPagamento(
                $parcela,
                $recebimento->valor,
                $recebimento->forma,
                parcelasCartao: $recebimento->parcelas_cartao,
            );
            // saldoRestante() fresco para a próxima linha (a última cai no restante).
            $parcela->refresh();
        }
    }

    public function cancelarUnico(Agendamento $agendamento): Agendamento
    {
        return DB::transaction(function () use ($agendamento) {
            $this->cancelarAgendamento->executar($agendamento);
            $this->estornarPagamentoSeExistir($agendamento->pagamento);

            return $agendamento->fresh();
        });
    }

    public function cancelarEtapas(VendaEtapas $etapas): VendaEtapas
    {
        return DB::transaction(function () use ($etapas) {
            $etapas->agendamentos()
                ->whereIn('status', ['agendado', 'confirmado'])
                ->update(['status' => 'cancelado']);

            $etapas->update(['status' => StatusVendaEtapas::Cancelado]);

            $this->estornarPagamentoSeExistir($etapas->pagamento);

            return $etapas->fresh();
        });
    }

    public function cancelarVendaProduto(VendaProduto $venda): VendaProduto
    {
        return DB::transaction(function () use ($venda) {
            $venda->load('itens');

            foreach ($venda->itens as $item) {
                Produto::find($item->produto_id)?->increment('quantidade', $item->quantidade);

                MovimentoEstoque::create([
                    'produto_id' => $item->produto_id,
                    'tipo' => 'entrada',
                    'quantidade' => $item->quantidade,
                ]);
            }

            $venda->update(['status' => StatusVendaProduto::Cancelada]);

            $this->estornarPagamentoSeExistir($venda->pagamento);

            return $venda->fresh();
        });
    }

    private function estornarPagamentoSeExistir(?Pagamento $pagamento): void
    {
        if (! $pagamento) {
            return;
        }
        $this->caixaService->estornarPagamento($pagamento);
    }

    /**
     * Remove uma venda de serviço único: desfaz os lançamentos (mesmo estorno
     * do cancelamento) e aplica soft delete no agendamento e no pagamento.
     * Se já estiver cancelada, apenas o soft delete é aplicado.
     */
    public function removerUnico(Agendamento $agendamento): void
    {
        DB::transaction(function () use ($agendamento) {
            if ($agendamento->status->value !== 'cancelado') {
                $this->cancelarUnico($agendamento);
                $agendamento->refresh();
            }

            $agendamento->pagamento?->delete();
            $agendamento->delete();
        });
    }

    /**
     * Remove uma venda em etapas: estorna (se ainda ativa) e aplica soft delete
     * na venda, no pagamento e nos agendamentos das sessões.
     */
    public function removerEtapas(VendaEtapas $etapas): void
    {
        DB::transaction(function () use ($etapas) {
            if ($etapas->status !== StatusVendaEtapas::Cancelado) {
                $this->cancelarEtapas($etapas);
                $etapas->refresh();
            }

            $etapas->agendamentos()->get()->each->delete();
            $etapas->pagamento?->delete();
            $etapas->delete();
        });
    }

    /**
     * Remove uma venda de produto: devolve estoque e estorna (se ainda ativa) e
     * aplica soft delete na venda e no pagamento.
     */
    public function removerVendaProduto(VendaProduto $venda): void
    {
        DB::transaction(function () use ($venda) {
            if ($venda->status !== StatusVendaProduto::Cancelada) {
                $this->cancelarVendaProduto($venda);
                $venda->refresh();
            }

            $venda->pagamento?->delete();
            $venda->delete();
        });
    }
}
