<?php

namespace App\Services;

use App\Actions\Agendamento\CancelarAgendamentoAction;
use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Pacote\VenderPacoteAction;
use App\DTO\Agendamento\CriarAgendamentoData;
use App\DTO\Pacote\VenderPacoteData;
use App\Enums\StatusVendaPacote;
use App\Models\Agendamento;
use App\Models\MovimentoEstoque;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\VendaPacote;
use App\Models\VendaProduto;
use Illuminate\Support\Collection;

class VendaService
{
    public function __construct(
        private CriarAgendamentoAction $criarAgendamento,
        private VenderPacoteAction $venderPacote,
        private CancelarAgendamentoAction $cancelarAgendamento,
    ) {}

    public function listar(): Collection
    {
        $pacotes = VendaPacote::with(['cliente', 'servico', 'profissional.usuario'])
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

        $avulsos = Agendamento::with(['cliente', 'servico', 'profissional.usuario'])
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

        $produtos = VendaProduto::with(['cliente', 'produto'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($vp) => (object) [
                'tipo' => 'produto',
                'id' => $vp->id,
                'cliente' => $vp->cliente->nome ?? '-',
                'servico' => $vp->produto->nome,
                'info' => $vp->quantidade . ' un.',
                'valor' => $vp->valor_total,
                'status' => 'concluido',
                'data' => $vp->created_at,
                'model' => $vp,
            ]);

        return $pacotes->merge($avulsos)->merge($produtos)->sortByDesc('data')->values();
    }

    public function criarAvulso(CriarAgendamentoData $data, string $formaPagamento, string $statusPagamento): Agendamento
    {
        $agendamento = $this->criarAgendamento->executar($data);
        $servico = Servico::find($data->servico_id);

        Pagamento::create([
            'agendamento_id' => $agendamento->id,
            'valor' => $servico->valor,
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        return $agendamento;
    }

    public function criarPacote(VenderPacoteData $data, string $formaPagamento, string $statusPagamento): VendaPacote
    {
        $pacote = $this->venderPacote->executar($data);

        Pagamento::create([
            'venda_pacote_id' => $pacote->id,
            'valor' => $data->valor_total,
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        return $pacote;
    }

    public function cancelarAvulso(Agendamento $agendamento): Agendamento
    {
        return $this->cancelarAgendamento->executar($agendamento);
    }

    public function cancelarPacote(VendaPacote $pacote): VendaPacote
    {
        $pacote->agendamentos()
            ->whereIn('status', ['agendado', 'confirmado'])
            ->update(['status' => 'cancelado']);

        $pacote->update(['status' => StatusVendaPacote::Cancelado]);

        return $pacote->fresh();
    }

    public function criarVendaProduto(int $cliente_id, int $produto_id, int $quantidade, float $valor_total, string $formaPagamento, string $statusPagamento): VendaProduto
    {
        $produto = Produto::findOrFail($produto_id);

        $venda = VendaProduto::create([
            'cliente_id' => $cliente_id ?: null,
            'produto_id' => $produto_id,
            'quantidade' => $quantidade,
            'valor_total' => $valor_total,
        ]);

        // Dar baixa no estoque
        MovimentoEstoque::create([
            'produto_id' => $produto_id,
            'tipo' => 'saida',
            'quantidade' => $quantidade,
        ]);

        $produto->decrement('quantidade', $quantidade);

        Pagamento::create([
            'venda_produto_id' => $venda->id,
            'valor' => $valor_total,
            'forma_pagamento' => $formaPagamento,
            'status' => $statusPagamento,
        ]);

        return $venda;
    }
}
