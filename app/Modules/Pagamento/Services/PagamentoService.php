<?php

namespace App\Modules\Pagamento\Services;

use App\Enums\StatusPagamento;
use App\Modules\Pagamento\Actions\RegistrarPagamentoAction;
use App\Modules\Pagamento\DTOs\RegistrarPagamentoData;
use App\Modules\Pagamento\Models\Pagamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class PagamentoService
{
    public function __construct(
        private RegistrarPagamentoAction $registrarPagamento,
    ) {}

    public function listar(string $filtro = 'todos'): Collection
    {
        $query = Pagamento::with('cliente')->orderByDesc('created_at');

        if ($filtro !== 'todos') {
            $query->where('status', $filtro);
        }

        return $query->get();
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
