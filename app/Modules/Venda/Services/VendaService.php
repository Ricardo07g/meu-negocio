<?php

namespace App\Modules\Venda\Services;

use App\Modules\Agenda\Actions\CancelarAgendamentoAction;
use App\Modules\Agenda\Actions\CriarAgendamentoAction;
use App\Modules\Venda\Actions\VenderPacoteAction;
use App\Modules\Agenda\DTOs\CriarAgendamentoData;
use App\Modules\Venda\DTOs\VenderPacoteData;
use App\Enums\StatusPagamento;
use App\Enums\StatusVendaPacote;
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

    public function listar(): Collection
    {
        $pacotes = VendaPacote::with(['cliente', 'servico', 'atendente'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($p) => (object) [
                'tipo' => 'pacote',
                'id' => $p->id,
                'cliente' => $p->cliente->nome,
                'servico' => $p->servico->nome,
                'info' => $p->qtd_sessoes . ' sessões',
                'valor' => $p->valor_total,
                'status' => $p->status->value,
                'data' => $p->created_at,
                'model' => $p,
            ]);

        $avulsos = Agendamento::with(['cliente', 'servico', 'atendente'])
            ->whereNull('venda_pacote_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => (object) [
                'tipo' => 'avulso',
                'id' => $a->id,
                'cliente' => $a->cliente->nome,
                'servico' => $a->servico->nome,
                'info' => $a->inicio->format('d/m/Y H:i'),
                'valor' => $a->servico->valor,
                'status' => $a->status->value,
                'data' => $a->created_at,
                'model' => $a,
            ]);

        $produtos = VendaProduto::with(['cliente', 'itens'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($vp) => (object) [
                'tipo' => 'produto',
                'id' => $vp->id,
                'cliente' => $vp->cliente->nome ?? '-',
                'servico' => $vp->itens->count() . ' produto(s)',
                'info' => $vp->itens->sum('quantidade') . ' un.',
                'valor' => $vp->valor_total,
                'status' => $vp->status,
                'data' => $vp->data ?? $vp->created_at,
                'model' => $vp,
            ]);

        return collect($pacotes)->merge($avulsos)->merge($produtos)->sortByDesc('data')->values();
    }

    public function criarAvulso(CriarAgendamentoData $data, string $formaPagamento, string $statusPagamento): Agendamento
    {
        $agendamento = $this->criarAgendamento->executar($data);
        $servico = Servico::find($data->servico_id);

        $pagamento = Pagamento::create([
            'cliente_id' => $data->cliente_id,
            'agendamento_id' => $agendamento->id,
            'valor' => $servico->valor,
            'valor_pago' => $statusPagamento === 'pago' ? $servico->valor : 0,
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        $this->registrarNoCaixaSeAberto($pagamento, $statusPagamento);

        return $agendamento;
    }

    public function criarPacote(VenderPacoteData $data, string $formaPagamento, string $statusPagamento): VendaPacote
    {
        $pacote = $this->venderPacote->executar($data);

        $pagamento = Pagamento::create([
            'cliente_id' => $data->cliente_id,
            'venda_pacote_id' => $pacote->id,
            'valor' => $data->valor_total,
            'valor_pago' => $statusPagamento === 'pago' ? $data->valor_total : 0,
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

            $venda->update(['status' => 'cancelada']);

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

    public function criarVendaProduto(?int $cliente_id, array $itens, string $formaPagamento, string $statusPagamento, ?string $data = null, ?string $observacao = null): VendaProduto
    {
        return DB::transaction(function () use ($cliente_id, $itens, $formaPagamento, $statusPagamento, $data, $observacao) {
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
                'status' => 'ativa',
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
                'forma_pagamento' => $formaPagamento,
                'status' => $statusPagamento,
            ]);

            $this->registrarNoCaixaSeAberto($pagamento, $statusPagamento);

            return $venda;
        });
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
            $pagamento->forma_pagamento->value,
        );
    }
}
