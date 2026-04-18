<?php

namespace App\Modules\Pagamento\Services;

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

    public function listar(): Collection
    {
        return Pagamento::with('cliente')->get();
    }

    public function buscar(int $id): Pagamento
    {
        return Pagamento::with('cliente')->findOrFail($id);
    }

    public function registrar(RegistrarPagamentoData $data): Pagamento
    {
        return $this->registrarPagamento->executar($data);
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Pagamento::with('cliente')
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
