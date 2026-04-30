<?php

namespace App\Modules\Estoque\Services;

use App\Enums\TipoMovimentoEstoque;
use App\Modules\Estoque\DTOs\RegistrarMovimentoData;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    public function registrarMovimento(RegistrarMovimentoData $data): MovimentoEstoque
    {
        return DB::transaction(function () use ($data) {
            $produto = Produto::findOrFail($data->produto_id);

            $movimento = MovimentoEstoque::create([
                'produto_id' => $data->produto_id,
                'tipo' => $data->tipo,
                'quantidade' => $data->quantidade,
            ]);

            match ($data->tipo) {
                TipoMovimentoEstoque::Entrada => $produto->increment('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Saida => $produto->decrement('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Ajuste => $produto->update(['quantidade' => $data->quantidade]),
            };

            return $movimento;
        });
    }

    public function listarMovimentos(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = MovimentoEstoque::with('produto')->orderBy('created_at', 'desc');

        if (! empty($filtros['produto_id'])) {
            $query->where('produto_id', $filtros['produto_id']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->whereHas('produto', function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        [$dataInicio, $dataFim] = $this->resolverPeriodo($filtros);

        if ($dataInicio) {
            $query->whereDate('created_at', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->whereDate('created_at', '<=', $dataFim);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function resolverPeriodo(array $filtros): array
    {
        $preset = $filtros['periodo_preset'] ?? null;

        return match ($preset) {
            'hoje' => [now()->toDateString(), now()->toDateString()],
            '7dias' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30dias' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            'mes_atual' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => [$filtros['data_inicio'] ?? null, $filtros['data_fim'] ?? null],
        };
    }
}
