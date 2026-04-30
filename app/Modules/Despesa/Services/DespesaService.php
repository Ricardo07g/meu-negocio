<?php

namespace App\Modules\Despesa\Services;

use App\Enums\StatusParcela;
use App\Exceptions\NegocioException;
use App\Modules\Despesa\Actions\CriarDespesaComParcelasAction;
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Modules\Pagamento\DTOs\RenegociarParcelaData;
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

    public function atualizar(Despesa $despesa, array $data): Despesa
    {
        $despesa->update([
            'categoria_despesa_id' => $data['categoria_despesa_id'] ?? $despesa->categoria_despesa_id,
            'nome' => $data['nome'] ?? $despesa->nome,
            'fornecedor_nome' => $data['fornecedor_nome'] ?? $despesa->fornecedor_nome,
            'documento' => $data['documento'] ?? $despesa->documento,
            'observacoes' => $data['observacoes'] ?? $despesa->observacoes,
            'mes_referencia' => $data['mes_referencia'] ?? $despesa->mes_referencia,
            'data_emissao' => $data['data_emissao'] ?? $despesa->data_emissao,
        ]);

        return $despesa->fresh();
    }

    public function excluir(Despesa $despesa): void
    {
        $despesa->delete();
    }

    public function renegociarParcela(ParcelaDespesa $parcela, RenegociarParcelaData $data): ParcelaDespesa
    {
        return DB::transaction(function () use ($parcela, $data) {
            if ($parcela->status === StatusParcela::Pago) {
                throw new NegocioException('Parcela já paga não pode ser renegociada.');
            }
            if ($parcela->status === StatusParcela::Cancelado) {
                throw new NegocioException('Parcela cancelada não pode ser renegociada.');
            }
            if ($data->valor < (float) $parcela->valor_pago) {
                throw new NegocioException('O novo valor não pode ser menor que o já pago.');
            }

            $observacaoAcumulada = trim(
                ($parcela->observacao ? $parcela->observacao."\n" : '')
                .sprintf(
                    '[Renegociado em %s] valor R$ %s → R$ %s; vencimento %s → %s. %s',
                    now()->format('d/m/Y H:i'),
                    number_format((float) $parcela->valor, 2, ',', '.'),
                    number_format($data->valor, 2, ',', '.'),
                    $parcela->data_vencimento?->format('d/m/Y') ?? '—',
                    $data->data_vencimento->format('d/m/Y'),
                    $data->observacao ? "Motivo: {$data->observacao}" : '',
                )
            );

            $parcela->update([
                'valor' => $data->valor,
                'data_vencimento' => $data->data_vencimento,
                'status' => (float) $parcela->valor_pago > 0 ? StatusParcela::Renegociado : StatusParcela::Pendente,
                'observacao' => $observacaoAcumulada,
            ]);

            $parcela->despesa?->recalcularStatus();

            return $parcela->fresh();
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
