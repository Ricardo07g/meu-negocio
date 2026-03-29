<?php

namespace App\Services;

use App\Actions\Pagamento\RegistrarPagamentoAction;
use App\DTO\Pagamento\RegistrarPagamentoData;
use App\Models\Pagamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class PagamentoService
{
    public function __construct(
        private RegistrarPagamentoAction $registrarPagamento,
    ) {}

    public function listar(): Collection
    {
        return Pagamento::with('agendamento.cliente')->get();
    }

    public function buscar(int $id): Pagamento
    {
        return Pagamento::with('agendamento.cliente')->findOrFail($id);
    }

    public function registrar(RegistrarPagamentoData $data): Pagamento
    {
        return $this->registrarPagamento->executar($data);
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Pagamento::with('agendamento.cliente')
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
