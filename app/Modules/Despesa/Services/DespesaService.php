<?php

declare(strict_types=1);

namespace App\Modules\Despesa\Services;

use App\Enums\{StatusDespesa, StatusParcela};
use App\Exceptions\NegocioException;
use App\Modules\Despesa\Actions\CriarDespesaComParcelasAction;
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Models\{Despesa, ParcelaDespesa};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DespesaService
{
    public function __construct(
        private CriarDespesaComParcelasAction $criarComParcelas,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Despesa::with(['categoria', 'parcelas.baixas'])
            ->orderByDesc('created_at');

        $status = $filtros['status'] ?? 'todas';
        if ($status !== 'todas' && $status !== '') {
            if ($status === 'vencidas') {
                $hoje = now()->toDateString();
                $query->whereIn('status', ['pendente', 'parcial'])
                    ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                        ->whereDate('data_vencimento', '<', $hoje));
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhere('nome', 'like', "%{$q}%")
                    ->orWhere('fornecedor_nome', 'like', "%{$q}%")
                    ->orWhere('documento', 'like', "%{$q}%");
            });
        }

        if (! empty($filtros['categoria_id'])) {
            $query->where('categoria_despesa_id', (int) $filtros['categoria_id']);
        }

        if (! empty($filtros['situacao'])) {
            $hoje = now()->toDateString();
            match ($filtros['situacao']) {
                'em_dia' => $query->whereIn('status', ['pendente', 'parcial'])
                    ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                        ->whereDate('data_vencimento', '>=', $hoje)),
                'vencida' => $query->whereIn('status', ['pendente', 'parcial'])
                    ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                        ->whereDate('data_vencimento', '<', $hoje)),
                default => null,
            };
        }

        if (! empty($filtros['mes_referencia'])) {
            $mes = $filtros['mes_referencia'];
            $query->whereRaw("DATE_FORMAT(mes_referencia, '%Y-%m') = ?", [$mes]);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function buscar(int $id): Despesa
    {
        return Despesa::findOrFail($id);
    }

    public function criar(CriarDespesaData $data): Despesa
    {
        return $this->criarComParcelas->executar($data);
    }

    public function excluir(Despesa $despesa): void
    {
        $despesa->delete();
    }

    /**
     * Cancela a despesa: marca todas as parcelas em aberto como canceladas
     * (Pendente + Renegociado), e o status agregado vira Cancelada se nao
     * houver parcela paga. Despesas ja pagas ou ja canceladas sao rejeitadas.
     */
    public function cancelarDespesa(Despesa $despesa, ?string $motivo = null): Despesa
    {
        return DB::transaction(function () use ($despesa, $motivo) {
            if ($despesa->status === StatusDespesa::Paga) {
                throw new NegocioException('Despesa já paga não pode ser cancelada.');
            }
            if ($despesa->status === StatusDespesa::Cancelada) {
                throw new NegocioException('Despesa já está cancelada.');
            }

            $sufixo = sprintf('[Cancelada com a despesa em %s] %s', now()->format('d/m/Y H:i'), $motivo ?? '');

            foreach ($despesa->parcelas as $parcela) {
                if (in_array($parcela->status, [StatusParcela::Pendente, StatusParcela::Renegociado], true)) {
                    $parcela->update([
                        'status' => StatusParcela::Cancelado,
                        'observacao' => trim(($parcela->observacao ? $parcela->observacao."\n" : '').$sufixo),
                    ]);
                }
            }

            $despesa->load('parcelas')->recalcularStatus();

            return $despesa->fresh();
        });
    }

    public function cancelarParcela(ParcelaDespesa $parcela, ?string $motivo = null): ParcelaDespesa
    {
        return DB::transaction(function () use ($parcela, $motivo) {
            if ($parcela->status === StatusParcela::Pago) {
                throw new NegocioException('Parcela já paga não pode ser cancelada.');
            }

            $observacao = $parcela->observacao ? $parcela->observacao."\n" : '';
            $observacao .= sprintf('[Cancelada em %s] %s', now()->format('d/m/Y H:i'), $motivo ?? '');

            $parcela->update([
                'status' => StatusParcela::Cancelado,
                'observacao' => trim($observacao),
            ]);

            $parcela->despesa?->recalcularStatus();

            return $parcela->fresh();
        });
    }
}
