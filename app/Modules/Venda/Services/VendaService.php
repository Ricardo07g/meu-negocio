<?php

namespace App\Modules\Venda\Services;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\FormaRecebimentoPrazo;
use App\Enums\StatusParcela;
use App\Enums\StatusVendaEtapas;
use App\Enums\StatusVendaProduto;
use App\Modules\Agenda\Actions\CancelarAgendamentoAction;
use App\Modules\Agenda\Actions\CriarAgendamentoAction;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Pagamento\Actions\CriarPagamentoComParcelasAction;
use App\Modules\Pagamento\DTOs\CriarPagamentoData;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Venda\Actions\CriarVendaProdutoAction;
use App\Modules\Venda\Actions\SincronizarItensVendaProdutoAction;
use App\Modules\Venda\Actions\VenderEtapasAction;
use App\Modules\Venda\DTOs\VenderEtapasData;
use App\Modules\Venda\Models\VendaEtapas;
use App\Modules\Venda\Models\VendaProduto;
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
        private SincronizarItensVendaProdutoAction $sincronizarItens,
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
            $etapas = $etapasQuery->get()->map(fn ($p) => (object) [
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
            ]);

            $unicosQuery = Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento.parcelas'])
                ->whereNull('venda_etapas_id')
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($unicosQuery, $filtros, $dataInicio, $dataFim, 'unico');
            $this->aplicarBuscaUnico($unicosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusUnico($unicosQuery, $filtros['status_venda'] ?? null);
            $this->aplicarValorUnico($unicosQuery, $filtros);
            $unicos = $unicosQuery->get()->map(fn ($a) => (object) [
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
            ]);
        }

        if ($tipo !== 'servico') {
            $produtosQuery = VendaProduto::with(['cliente', 'usuario', 'itens.produto', 'pagamento.parcelas'])
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($produtosQuery, $filtros, $dataInicio, $dataFim, 'produto');
            $this->aplicarBuscaProduto($produtosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusProduto($produtosQuery, $filtros['status_venda'] ?? null);
            $produtos = $produtosQuery->get()->map(fn ($vp) => (object) [
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
            ]);
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
                    $q->whereHas('parcelas', fn ($p) => $p->where('forma_pagamento', $forma));
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

    /**
     * Cria venda de serviço único + pagamento (título + parcelas).
     */
    public function criarUnico(
        AgendamentoData $data,
        CondicaoPagamento $condicao,
        Carbon $mesReferencia,
        ?FormaPagamento $formaAvista = null,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): Agendamento {
        return DB::transaction(function () use ($data, $condicao, $mesReferencia, $formaAvista, $numeroParcelas, $primeiroVencimento, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
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
                forma_pagamento_avista: $formaAvista,
                forma_recebimento_prazo: $formaRecebimentoPrazo,
                parcelas_personalizadas: $parcelasPersonalizadas,
            ));

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $formaAvista);

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
        ?FormaPagamento $formaAvista = null,
        ?int $numeroParcelas = null,
        ?Carbon $primeiroVencimento = null,
        ?array $parcelasPersonalizadas = null,
        ?FormaRecebimentoPrazo $formaRecebimentoPrazo = null,
    ): VendaEtapas {
        return DB::transaction(function () use ($data, $condicao, $mesReferencia, $formaAvista, $numeroParcelas, $primeiroVencimento, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
            $etapas = $this->venderEtapas->executar($data);

            $pagamento = $this->criarPagamento->executar(new CriarPagamentoData(
                valor_total: (float) $data->valor_total,
                condicao_pagamento: $condicao,
                mes_referencia: $mesReferencia,
                cliente_id: $data->cliente_id,
                venda_etapas_id: $etapas->id,
                numero_parcelas: $numeroParcelas,
                primeiro_vencimento: $primeiroVencimento ?? now(),
                forma_pagamento_avista: $formaAvista,
                forma_recebimento_prazo: $formaRecebimentoPrazo,
                parcelas_personalizadas: $parcelasPersonalizadas,
            ));

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $formaAvista);

            return $etapas;
        });
    }

    public function criarVendaProduto(
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
    ): VendaProduto {
        return DB::transaction(function () use ($cliente_id, $itens, $condicao, $mesReferencia, $formaAvista, $numeroParcelas, $primeiroVencimento, $data, $observacao, $parcelasPersonalizadas, $formaRecebimentoPrazo) {
            ['venda' => $venda, 'pagamento' => $pagamento] = $this->criarVendaProdutoAction->executar(
                cliente_id: $cliente_id,
                itens: $itens,
                condicao: $condicao,
                mesReferencia: $mesReferencia,
                formaAvista: $formaAvista,
                numeroParcelas: $numeroParcelas,
                primeiroVencimento: $primeiroVencimento,
                data: $data,
                observacao: $observacao,
                parcelasPersonalizadas: $parcelasPersonalizadas,
                formaRecebimentoPrazo: $formaRecebimentoPrazo,
            );

            $this->baixarAVistaSeAplicavel($pagamento, $condicao, $formaAvista);

            return $venda;
        });
    }

    /**
     * Se for venda à vista, aplica baixa automática na parcela única.
     * Requer caixa aberto.
     */
    private function baixarAVistaSeAplicavel(Pagamento $pagamento, CondicaoPagamento $condicao, ?FormaPagamento $forma): void
    {
        if ($condicao !== CondicaoPagamento::AVista || ! $forma) {
            return;
        }

        $parcela = $pagamento->parcelas->first();
        if (! $parcela) {
            return;
        }

        $this->caixaService->darBaixaParcelaPagamento(
            $parcela,
            (float) $parcela->valor,
            $forma,
        );
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

    public function podeEditar(?Pagamento $pagamento): bool
    {
        if (! $pagamento) {
            return true;
        }

        return $pagamento->valorPago() <= 0.0;
    }

    public function atualizarUnico(Agendamento $agendamento, array $data): Agendamento
    {
        return DB::transaction(function () use ($agendamento, $data) {
            if (! in_array($agendamento->status->value, ['agendado', 'confirmado'])) {
                throw new \DomainException('Agendamento não pode ser editado nesse status.');
            }
            if (! $this->podeEditar($agendamento->pagamento)) {
                throw new \DomainException('Venda já possui parcelas pagas; edição bloqueada.');
            }

            $agendamento->update([
                'cliente_id' => $data['cliente_id'] ?? $agendamento->cliente_id,
                'observacoes' => $data['observacao'] ?? null,
            ]);

            if ($agendamento->pagamento && isset($data['cliente_id'])) {
                $agendamento->pagamento->update(['cliente_id' => $data['cliente_id']]);
            }

            return $agendamento->fresh();
        });
    }

    public function atualizarEtapas(VendaEtapas $etapas, array $data): VendaEtapas
    {
        return DB::transaction(function () use ($etapas, $data) {
            if ($etapas->status !== StatusVendaEtapas::Ativo) {
                throw new \DomainException('Venda em etapas não pode ser editada nesse status.');
            }
            if (! $this->podeEditar($etapas->pagamento)) {
                throw new \DomainException('Venda já possui parcelas pagas; edição bloqueada.');
            }

            $desconto = (float) ($data['desconto'] ?? $etapas->desconto);
            $acrescimo = (float) ($data['acrescimo'] ?? $etapas->acrescimo);
            $subtotal = (float) $etapas->valor_total + (float) $etapas->desconto - (float) $etapas->acrescimo;
            $novoValorTotal = $subtotal - $desconto + $acrescimo;

            $etapas->update([
                'cliente_id' => $data['cliente_id'] ?? $etapas->cliente_id,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'valor_total' => $novoValorTotal,
                'observacao' => $data['observacao'] ?? null,
            ]);

            if ($etapas->pagamento) {
                $updates = ['valor_total' => $novoValorTotal];
                if (isset($data['cliente_id'])) {
                    $updates['cliente_id'] = $data['cliente_id'];
                }
                $etapas->pagamento->update($updates);

                // Se for parcela única (à vista) ou qualquer 1 parcela, propagar o novo valor
                if ($etapas->pagamento->parcelas->count() === 1) {
                    $etapas->pagamento->parcelas->first()->update(['valor' => $novoValorTotal]);
                }
            }

            return $etapas->fresh();
        });
    }

    public function atualizarVendaProduto(VendaProduto $venda, array $data): VendaProduto
    {
        return DB::transaction(function () use ($venda, $data) {
            if ($venda->status !== StatusVendaProduto::Ativa) {
                throw new \DomainException('Venda não pode ser editada nesse status.');
            }
            if (! $this->podeEditar($venda->pagamento)) {
                throw new \DomainException('Venda já possui parcelas pagas; edição bloqueada.');
            }

            $venda->load('itens');

            if (isset($data['itens']) && is_array($data['itens'])) {
                $this->sincronizarItens->executar($venda, $data['itens']);
                $venda->refresh();
                $venda->load('itens');
            }

            $subtotal = $venda->itens->sum('subtotal');
            $desconto = (float) ($data['desconto'] ?? $venda->desconto);
            $acrescimo = (float) ($data['acrescimo'] ?? $venda->acrescimo);
            $valorTotal = $subtotal - $desconto + $acrescimo;

            $venda->update([
                'cliente_id' => $data['cliente_id'] ?? $venda->cliente_id,
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'valor_total' => $valorTotal,
                'observacao' => $data['observacao'] ?? $venda->observacao,
            ]);

            if ($venda->pagamento) {
                $updates = ['valor_total' => $valorTotal];
                if (isset($data['cliente_id'])) {
                    $updates['cliente_id'] = $data['cliente_id'];
                }
                $venda->pagamento->update($updates);

                if ($venda->pagamento->parcelas->count() === 1) {
                    $venda->pagamento->parcelas->first()->update(['valor' => $valorTotal]);
                }
            }

            return $venda->fresh()->load('itens');
        });
    }
}
