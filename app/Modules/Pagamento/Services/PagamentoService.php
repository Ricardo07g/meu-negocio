<?php

namespace App\Modules\Pagamento\Services;

use App\Enums\StatusPagamento;
use App\Modules\Pagamento\Actions\RegistrarPagamentoAction;
use App\Modules\Pagamento\DTOs\RegistrarPagamentoData;
use App\Modules\Pagamento\Models\Pagamento;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PagamentoService
{
    public function __construct(
        private RegistrarPagamentoAction $registrarPagamento,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Pagamento::with([
            'cliente',
            'agendamento.servico',
            'vendaPacote.servico',
            'vendaProduto.itens',
            'baixas',
        ])->orderByDesc('created_at');

        $status = $filtros['status'] ?? 'todos';
        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        if (!empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$q}%"));
            });
        }

        if (!empty($filtros['origem'])) {
            match ($filtros['origem']) {
                'avulso' => $query->whereNotNull('agendamento_id'),
                'pacote' => $query->whereNotNull('venda_pacote_id'),
                'produto' => $query->whereNotNull('venda_produto_id'),
                default => null,
            };
        }

        if (!empty($filtros['situacao'])) {
            $hoje = now()->toDateString();
            match ($filtros['situacao']) {
                'em_dia' => $query->where('status', 'pendente')->where(fn ($q) => $q->whereNull('data_vencimento')->orWhereDate('data_vencimento', '>=', $hoje)),
                'vencido' => $query->where('status', 'pendente')->whereDate('data_vencimento', '<', $hoje),
                default => null,
            };
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function buscar(int $id): Pagamento
    {
        return Pagamento::with('cliente')->findOrFail($id);
    }

    public function registrar(RegistrarPagamentoData $data): Pagamento
    {
        return $this->registrarPagamento->executar($data);
    }

    public function listarContasAReceber(): Collection
    {
        return Pagamento::with('cliente')
            ->where('status', StatusPagamento::Pendente)
            ->whereRaw('valor_pago < valor')
            ->orderByDesc('created_at')
            ->get();
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Pagamento::with('cliente')
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
