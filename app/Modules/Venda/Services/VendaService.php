<?php

namespace App\Modules\Venda\Services;

use App\Modules\Agenda\Actions\CancelarAgendamentoAction;
use App\Modules\Agenda\Actions\CriarAgendamentoAction;
use App\Modules\Venda\Actions\VenderPacoteAction;
use App\Modules\Agenda\DTOs\CriarAgendamentoData;
use App\Modules\Venda\DTOs\VenderPacoteData;
use App\Enums\StatusPagamento;
use App\Enums\StatusVendaPacote;
use App\Enums\StatusVendaProduto;
use App\Enums\TipoMovimentoCaixa;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Models\VendaProduto;
use App\Modules\Venda\Models\VendaProdutoItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendaService
{
    public function __construct(
        private CriarAgendamentoAction $criarAgendamento,
        private VenderPacoteAction $venderPacote,
        private CancelarAgendamentoAction $cancelarAgendamento,
        private CaixaService $caixaService,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $tipo = $filtros['tipo'] ?? 'todos';
        [$dataInicio, $dataFim] = $this->resolverPeriodo($filtros);

        $pacotes = collect();
        $avulsos = collect();
        $produtos = collect();

        if ($tipo !== 'produto') {
            $pacotesQuery = VendaPacote::with(['cliente', 'servico', 'atendente', 'agendamentos', 'pagamento.baixas'])
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($pacotesQuery, $filtros, $dataInicio, $dataFim, 'pacote');
            $this->aplicarBuscaPacote($pacotesQuery, $filtros['q'] ?? null);
            $this->aplicarStatusPacote($pacotesQuery, $filtros['status_venda'] ?? null);
            $pacotes = $pacotesQuery->get()->map(fn ($p) => (object) [
                'tipo' => 'pacote',
                'id' => $p->id,
                'cliente' => $p->cliente->nome,
                'servico' => $p->servico->nome,
                'info' => $p->qtd_sessoes . ' sessões',
                'valor' => $p->valor_total,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'cor' => $p->status->cor(),
                'data' => $p->created_at,
                'model' => $p,
            ]);

            $avulsosQuery = Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento.baixas'])
                ->whereNull('venda_pacote_id')
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($avulsosQuery, $filtros, $dataInicio, $dataFim, 'avulso');
            $this->aplicarBuscaAvulso($avulsosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusAvulso($avulsosQuery, $filtros['status_venda'] ?? null);
            $this->aplicarValorAvulso($avulsosQuery, $filtros);
            $avulsos = $avulsosQuery->get()->map(fn ($a) => (object) [
                'tipo' => 'avulso',
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
            $produtosQuery = VendaProduto::with(['cliente', 'usuario', 'itens.produto', 'pagamento.baixas'])
                ->orderByDesc('created_at');
            $this->aplicarFiltrosComuns($produtosQuery, $filtros, $dataInicio, $dataFim, 'produto');
            $this->aplicarBuscaProduto($produtosQuery, $filtros['q'] ?? null);
            $this->aplicarStatusProduto($produtosQuery, $filtros['status_venda'] ?? null);
            $produtos = $produtosQuery->get()->map(fn ($vp) => (object) [
                'tipo' => 'produto',
                'id' => $vp->id,
                'cliente' => $vp->cliente->nome ?? '-',
                'servico' => $vp->itens->count() . ' produto(s)',
                'info' => $vp->itens->sum('quantidade') . ' un.',
                'valor' => $vp->valor_total,
                'status' => $vp->status->value,
                'status_label' => $vp->status->label(),
                'cor' => $vp->status->cor(),
                'data' => $vp->data ?? $vp->created_at,
                'model' => $vp,
            ]);
        }

        $vendas = collect($pacotes)->merge($avulsos)->merge($produtos)->sortByDesc('data')->values();

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

        if (!empty($filtros['data_inicio'])) {
            $inicio = \Carbon\Carbon::parse($filtros['data_inicio'])->startOfDay();
        }
        if (!empty($filtros['data_fim'])) {
            $fim = \Carbon\Carbon::parse($filtros['data_fim'])->endOfDay();
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

        if (!empty($filtros['situacao_pagamento'])) {
            $situacao = $filtros['situacao_pagamento'];
            $hoje = now()->toDateString();
            $query->whereHas('pagamento', function ($q) use ($situacao, $hoje) {
                match ($situacao) {
                    'pago' => $q->where('status', 'pago'),
                    'pendente' => $q->where('status', 'pendente')->where(fn ($sub) => $sub->whereNull('data_vencimento')->orWhereDate('data_vencimento', '>=', $hoje)),
                    'vencido' => $q->where('status', 'pendente')->whereDate('data_vencimento', '<', $hoje),
                    'estornado' => $q->where('status', 'estornado'),
                    default => null,
                };
            });
        }

        if (!empty($filtros['forma_pagamento'])) {
            $forma = $filtros['forma_pagamento'];
            $query->whereHas('pagamento', function ($q) use ($forma) {
                if ($forma === 'fiado') {
                    $q->whereNull('forma_pagamento');
                } else {
                    $q->where('forma_pagamento', $forma);
                }
            });
        }

        if (!empty($filtros['atendente_id'])) {
            $atendenteId = (int) $filtros['atendente_id'];
            if ($origem === 'produto') {
                $query->where('usuario_id', $atendenteId);
            } else {
                $query->where('atendente_id', $atendenteId);
            }
        }

        if ($origem !== 'avulso') {
            if (!empty($filtros['valor_min'])) {
                $query->where('valor_total', '>=', (float) $filtros['valor_min']);
            }
            if (!empty($filtros['valor_max'])) {
                $query->where('valor_total', '<=', (float) $filtros['valor_max']);
            }
        }
    }

    private function aplicarValorAvulso($query, array $filtros): void
    {
        // Avulso nao tem valor_total proprio — filtra pelo servico.valor
        if (!empty($filtros['valor_min'])) {
            $query->whereHas('servico', fn ($q) => $q->where('valor', '>=', (float) $filtros['valor_min']));
        }
        if (!empty($filtros['valor_max'])) {
            $query->whereHas('servico', fn ($q) => $q->where('valor', '<=', (float) $filtros['valor_max']));
        }
    }

    private function aplicarBuscaPacote($query, ?string $q): void
    {
        if (!$q) return;
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('servico', fn ($r) => $r->where('nome', 'like', "%{$q}%"));
        });
    }

    private function aplicarBuscaAvulso($query, ?string $q): void
    {
        if (!$q) return;
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('servico', fn ($r) => $r->where('nome', 'like', "%{$q}%"));
        });
    }

    private function aplicarBuscaProduto($query, ?string $q): void
    {
        if (!$q) return;
        $query->where(function ($sub) use ($q) {
            $sub->where('id', $q)
                ->orWhereHas('cliente', fn ($r) => $r->where('nome', 'like', "%{$q}%"))
                ->orWhereHas('itens', fn ($r) => $r->where('descricao', 'like', "%{$q}%"));
        });
    }

    private function aplicarStatusPacote($query, ?string $status): void
    {
        match ($status) {
            'em_andamento' => $query->where('status', 'ativo'),
            'concluido' => $query->where('status', 'concluido'),
            'cancelado' => $query->where('status', 'cancelado'),
            default => null,
        };
    }

    private function aplicarStatusAvulso($query, ?string $status): void
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
            // 'concluido' nao existe pra VendaProduto — retorna vazio
            'concluido' => $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    public function criarAvulso(CriarAgendamentoData $data, ?string $formaPagamento, string $statusPagamento, ?string $dataVencimento = null): Agendamento
    {
        $agendamento = $this->criarAgendamento->executar($data);
        $servico = Servico::find($data->servico_id);

        $pagamento = Pagamento::create([
            'cliente_id' => $data->cliente_id,
            'agendamento_id' => $agendamento->id,
            'valor' => $servico->valor,
            'valor_pago' => $statusPagamento === 'pago' ? $servico->valor : 0,
            'data_vencimento' => $this->resolverVencimento($statusPagamento, $dataVencimento),
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        $this->registrarNoCaixaSeAberto($pagamento, $statusPagamento);

        return $agendamento;
    }

    public function criarPacote(VenderPacoteData $data, ?string $formaPagamento, string $statusPagamento, ?string $dataVencimento = null): VendaPacote
    {
        $pacote = $this->venderPacote->executar($data);

        $pagamento = Pagamento::create([
            'cliente_id' => $data->cliente_id,
            'venda_pacote_id' => $pacote->id,
            'valor' => $data->valor_total,
            'valor_pago' => $statusPagamento === 'pago' ? $data->valor_total : 0,
            'data_vencimento' => $this->resolverVencimento($statusPagamento, $dataVencimento),
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        $this->registrarNoCaixaSeAberto($pagamento, $statusPagamento);

        return $pacote;
    }

    public function cancelarAvulso(Agendamento $agendamento): Agendamento
    {
        return DB::transaction(function () use ($agendamento) {
            $this->cancelarAgendamento->executar($agendamento);
            $this->estornarPagamento($agendamento->pagamento);

            return $agendamento->fresh();
        });
    }

    public function cancelarPacote(VendaPacote $pacote): VendaPacote
    {
        return DB::transaction(function () use ($pacote) {
            $pacote->agendamentos()
                ->whereIn('status', ['agendado', 'confirmado'])
                ->update(['status' => 'cancelado']);

            $pacote->update(['status' => StatusVendaPacote::Cancelado]);

            $pagamento = Pagamento::where('venda_pacote_id', $pacote->id)->first();
            $this->estornarPagamento($pagamento);

            return $pacote->fresh();
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

            $pagamento = Pagamento::where('venda_produto_id', $venda->id)->first();
            $this->estornarPagamento($pagamento);

            return $venda->fresh();
        });
    }

    private function estornarPagamento(?Pagamento $pagamento): void
    {
        if (!$pagamento) {
            return;
        }

        $estavaPago = $pagamento->status === StatusPagamento::Pago;
        $pagamento->update(['status' => StatusPagamento::Estornado]);

        if ($estavaPago && $pagamento->valor_pago > 0) {
            $caixa = $this->caixaService->caixaAberto();
            if ($caixa) {
                MovimentoCaixa::create([
                    'caixa_id' => $caixa->id,
                    'tipo' => TipoMovimentoCaixa::Saida,
                    'valor' => $pagamento->valor_pago,
                    'descricao' => "Estorno pagamento #{$pagamento->id}",
                ]);
            }
        }
    }

    public function criarVendaProduto(?int $cliente_id, array $itens, ?string $formaPagamento, string $statusPagamento, ?string $data = null, ?string $observacao = null, ?string $dataVencimento = null): VendaProduto
    {
        return DB::transaction(function () use ($cliente_id, $itens, $formaPagamento, $statusPagamento, $data, $observacao, $dataVencimento) {
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

            $pagamento = Pagamento::create([
                'cliente_id' => $cliente_id ?: null,
                'venda_produto_id' => $venda->id,
                'valor' => $venda->valor_total,
                'valor_pago' => $statusPagamento === 'pago' ? $venda->valor_total : 0,
                'data_vencimento' => $this->resolverVencimento($statusPagamento, $dataVencimento),
                'forma_pagamento' => $formaPagamento,
                'status' => $statusPagamento,
            ]);

            $this->registrarNoCaixaSeAberto($pagamento, $statusPagamento);

            return $venda;
        });
    }

    private function resolverVencimento(string $statusPagamento, ?string $dataVencimento): string
    {
        if ($statusPagamento === 'pago') {
            return now()->toDateString();
        }

        return $dataVencimento ?: now()->addDays(30)->toDateString();
    }

    private function registrarNoCaixaSeAberto(Pagamento $pagamento, string $statusPagamento): void
    {
        if ($statusPagamento !== 'pago') {
            return;
        }

        $caixa = $this->caixaService->caixaAberto();
        if (!$caixa) {
            return;
        }

        $this->caixaService->registrarEntrada(
            $caixa,
            $pagamento->valor,
            "Venda - Pagamento #{$pagamento->id}",
            $pagamento->forma_pagamento?->value,
        );
    }

    public function podeEditar(?Pagamento $pagamento): bool
    {
        if (!$pagamento) {
            return true;
        }

        return (float) $pagamento->valor_pago === 0.0;
    }

    public function atualizarAvulso(Agendamento $agendamento, array $data): Agendamento
    {
        return DB::transaction(function () use ($agendamento, $data) {
            if (!in_array($agendamento->status->value, ['agendado', 'confirmado'])) {
                throw new \DomainException('Agendamento não pode ser editado nesse status.');
            }
            if (!$this->podeEditar($agendamento->pagamento)) {
                throw new \DomainException('Venda já possui baixas; edição bloqueada.');
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

    public function atualizarPacote(VendaPacote $pacote, array $data): VendaPacote
    {
        return DB::transaction(function () use ($pacote, $data) {
            if ($pacote->status !== StatusVendaPacote::Ativo) {
                throw new \DomainException('Pacote não pode ser editado nesse status.');
            }
            if (!$this->podeEditar($pacote->pagamento)) {
                throw new \DomainException('Venda já possui baixas; edição bloqueada.');
            }

            $desconto = (float) ($data['desconto'] ?? $pacote->desconto);
            $acrescimo = (float) ($data['acrescimo'] ?? $pacote->acrescimo);
            $subtotal = (float) $pacote->valor_total + (float) $pacote->desconto - (float) $pacote->acrescimo;
            $novoValorTotal = $subtotal - $desconto + $acrescimo;

            $pacote->update([
                'cliente_id' => $data['cliente_id'] ?? $pacote->cliente_id,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'valor_total' => $novoValorTotal,
                'observacao' => $data['observacao'] ?? null,
            ]);

            if ($pacote->pagamento) {
                $updates = ['valor' => $novoValorTotal];
                if (isset($data['cliente_id'])) {
                    $updates['cliente_id'] = $data['cliente_id'];
                }
                $pacote->pagamento->update($updates);
            }

            return $pacote->fresh();
        });
    }

    public function atualizarVendaProduto(VendaProduto $venda, array $data): VendaProduto
    {
        return DB::transaction(function () use ($venda, $data) {
            if ($venda->status !== StatusVendaProduto::Ativa) {
                throw new \DomainException('Venda não pode ser editada nesse status.');
            }
            if (!$this->podeEditar($venda->pagamento)) {
                throw new \DomainException('Venda já possui baixas; edição bloqueada.');
            }

            $venda->load('itens');

            if (isset($data['itens']) && is_array($data['itens'])) {
                $this->sincronizarItensVendaProduto($venda, $data['itens']);
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
                $updates = ['valor' => $valorTotal];
                if (isset($data['cliente_id'])) {
                    $updates['cliente_id'] = $data['cliente_id'];
                }
                $venda->pagamento->update($updates);
            }

            return $venda->fresh()->load('itens');
        });
    }

    private function sincronizarItensVendaProduto(VendaProduto $venda, array $novoItens): void
    {
        $antigosPorId = $venda->itens->keyBy('id');
        $idsManipulados = [];

        foreach ($novoItens as $entrada) {
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
