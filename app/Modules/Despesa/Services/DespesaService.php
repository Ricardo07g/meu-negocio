<?php

namespace App\Modules\Despesa\Services;

use App\Enums\StatusDespesa;
use App\Modules\Despesa\Actions\CriarDespesaParceladaAction;
use App\Modules\Despesa\DTOs\DespesaData;
use App\Modules\Despesa\Models\Despesa;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DespesaService
{
    public function __construct(
        private CriarDespesaParceladaAction $criarParcelada,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Despesa::with(['categoria', 'baixas'])
            ->orderBy('data_vencimento', 'asc');

        $status = $filtros['status'] ?? 'todas';
        if ($status === 'vencidas') {
            $query->where('status', StatusDespesa::Pendente)
                ->whereDate('data_vencimento', '<', now()->toDateString());
        } elseif (in_array($status, ['pendente', 'paga', 'cancelada'], true)) {
            $query->where('status', $status);
        }

        if (!empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhere('nome', 'like', "%{$q}%")
                    ->orWhere('fornecedor_nome', 'like', "%{$q}%")
                    ->orWhere('documento', 'like', "%{$q}%");
            });
        }

        if (!empty($filtros['categoria_id'])) {
            $query->where('categoria_despesa_id', (int) $filtros['categoria_id']);
        }

        if (!empty($filtros['situacao'])) {
            $hoje = now()->toDateString();
            match ($filtros['situacao']) {
                'em_dia' => $query->where('status', StatusDespesa::Pendente)->where(fn ($q) => $q->whereNull('data_vencimento')->orWhereDate('data_vencimento', '>=', $hoje)),
                'vencida' => $query->where('status', StatusDespesa::Pendente)->whereDate('data_vencimento', '<', $hoje),
                default => null,
            };
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function buscar(int $id): Despesa
    {
        return Despesa::findOrFail($id);
    }

    public function criar(DespesaData $data): Despesa|Collection
    {
        if ($data->parcelar && $data->numero_parcelas && $data->numero_parcelas >= 2) {
            return $this->criarParcelada->executar($data);
        }

        return Despesa::create([
            'categoria_despesa_id' => $data->categoria_despesa_id,
            'nome' => $data->nome,
            'fornecedor_nome' => $data->fornecedor_nome,
            'documento' => $data->documento,
            'observacoes' => $data->observacoes,
            'valor' => $data->valor,
            'valor_pago' => 0,
            'data_emissao' => $data->data_emissao,
            'data_vencimento' => $data->data_vencimento,
            'competencia' => $data->competencia,
            'status' => 'pendente',
        ]);
    }

    public function atualizar(Despesa $despesa, DespesaData $data): Despesa
    {
        $despesa->update([
            'categoria_despesa_id' => $data->categoria_despesa_id,
            'nome' => $data->nome,
            'fornecedor_nome' => $data->fornecedor_nome,
            'documento' => $data->documento,
            'observacoes' => $data->observacoes,
            'valor' => $data->valor,
            'data_emissao' => $data->data_emissao,
            'data_vencimento' => $data->data_vencimento,
            'competencia' => $data->competencia,
        ]);

        return $despesa->fresh();
    }

    public function excluir(Despesa $despesa): void
    {
        $despesa->delete();
    }

    public function listarContasAPagar(): Collection
    {
        return Despesa::with(['categoria', 'baixas'])
            ->where('status', StatusDespesa::Pendente)
            ->whereRaw('valor_pago < valor')
            ->orderBy('data_vencimento', 'asc')
            ->get();
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Despesa::whereBetween('data_vencimento', [$inicio, $fim])
            ->orderBy('data_vencimento', 'desc')
            ->get();
    }
}
