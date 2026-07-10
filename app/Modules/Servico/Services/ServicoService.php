<?php

declare(strict_types=1);

namespace App\Modules\Servico\Services;

use App\Modules\Servico\DTOs\ServicoData;
use App\Modules\Servico\Models\Servico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServicoService
{
    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Servico::query()->with('arquivoPrincipal')->orderBy('nome');

        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('descricao', 'like', "%{$q}%");
            });
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['valor_min'])) {
            $query->where('valor', '>=', $filtros['valor_min']);
        }

        if (! empty($filtros['valor_max'])) {
            $query->where('valor', '<=', $filtros['valor_max']);
        }

        if (! empty($filtros['duracao_min'])) {
            $query->where('duracao', '>=', $filtros['duracao_min']);
        }

        if (! empty($filtros['duracao_max'])) {
            $query->where('duracao', '<=', $filtros['duracao_max']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function buscar(int $id): Servico
    {
        return Servico::findOrFail($id);
    }

    public function criar(ServicoData $data): Servico
    {
        return Servico::create([
            'nome' => $data->nome,
            'duracao' => $data->duracao,
            'valor' => $data->valor,
            'tipo' => $data->tipo,
            'qtd_etapas' => $data->qtd_etapas,
            'descricao' => $data->descricao,
        ]);
    }

    public function atualizar(Servico $servico, ServicoData $data): Servico
    {
        $servico->update([
            'nome' => $data->nome,
            'duracao' => $data->duracao,
            'valor' => $data->valor,
            'tipo' => $data->tipo,
            'qtd_etapas' => $data->qtd_etapas,
            'descricao' => $data->descricao,
        ]);

        return $servico->fresh();
    }

    public function excluir(Servico $servico): void
    {
        $servico->delete();
    }
}
