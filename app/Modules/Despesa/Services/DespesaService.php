<?php

namespace App\Modules\Despesa\Services;

use App\Modules\Despesa\DTOs\AtualizarDespesaData;
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Models\Despesa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DespesaService
{
    public function listar(): Collection
    {
        return Despesa::orderBy('data', 'desc')->get();
    }

    public function buscar(int $id): Despesa
    {
        return Despesa::findOrFail($id);
    }

    public function criar(CriarDespesaData $data): Despesa
    {
        return Despesa::create([
            'nome' => $data->nome,
            'valor' => $data->valor,
            'data' => $data->data,
        ]);
    }

    public function atualizar(Despesa $despesa, AtualizarDespesaData $data): Despesa
    {
        $despesa->update([
            'nome' => $data->nome,
            'valor' => $data->valor,
            'data' => $data->data,
        ]);

        return $despesa->fresh();
    }

    public function excluir(Despesa $despesa): void
    {
        $despesa->delete();
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Despesa::whereBetween('data', [$inicio, $fim])
            ->orderBy('data', 'desc')
            ->get();
    }
}
