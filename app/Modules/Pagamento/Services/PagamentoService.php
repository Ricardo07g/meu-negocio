<?php

declare(strict_types=1);

namespace App\Modules\Pagamento\Services;

use App\Enums\StatusParcela;
use App\Exceptions\NegocioException;
use App\Modules\Pagamento\DTOs\RenegociarParcelaData;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PagamentoService
{
    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Pagamento::with([
            'cliente',
            'agendamento.servico',
            'vendaEtapas.servico',
            'vendaProduto.itens',
            'parcelas.baixas',
        ])->orderByDesc('created_at');

        $status = $filtros['status'] ?? 'todos';
        if ($status !== 'todos' && $status !== '') {
            $query->where('status', $status);
        }

        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$q}%"));
            });
        }

        if (! empty($filtros['origem'])) {
            match ($filtros['origem']) {
                'unico' => $query->whereNotNull('agendamento_id'),
                'etapas' => $query->whereNotNull('venda_etapas_id'),
                'produto' => $query->whereNotNull('venda_produto_id'),
                default => null,
            };
        }

        if (! empty($filtros['situacao'])) {
            $hoje = now()->toDateString();
            match ($filtros['situacao']) {
                'em_dia' => $query->whereIn('status', ['pendente', 'parcial'])
                    ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                        ->whereDate('data_vencimento', '>=', $hoje)),
                'vencido' => $query->whereIn('status', ['pendente', 'parcial'])
                    ->whereHas('parcelas', fn ($p) => $p->where('status', StatusParcela::Pendente->value)
                        ->whereDate('data_vencimento', '<', $hoje)),
                default => null,
            };
        }

        if (! empty($filtros['mes_referencia'])) {
            $mes = $filtros['mes_referencia']; // formato Y-m
            $query->whereRaw("DATE_FORMAT(mes_referencia, '%Y-%m') = ?", [$mes]);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function buscar(int $id): Pagamento
    {
        return Pagamento::with(['cliente', 'parcelas'])->findOrFail($id);
    }

    /**
     * Renegocia uma parcela: ajusta valor e/ou vencimento, marca como renegociada.
     */
    public function renegociarParcela(ParcelaPagamento $parcela, RenegociarParcelaData $data): ParcelaPagamento
    {
        return DB::transaction(function () use ($parcela, $data) {
            if ($parcela->status === StatusParcela::Pago) {
                throw new NegocioException('Parcela já paga não pode ser renegociada.');
            }
            if ($parcela->status === StatusParcela::Cancelado) {
                throw new NegocioException('Parcela cancelada não pode ser renegociada.');
            }
            if ($data->valor <= 0) {
                throw new NegocioException('Valor da parcela deve ser positivo.');
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

            $parcela->pagamento?->recalcularStatus();

            return $parcela->fresh();
        });
    }

    /**
     * Cancela uma parcela específica (cliente desistiu, acordo parcial, etc).
     */
    public function cancelarParcela(ParcelaPagamento $parcela, ?string $motivo = null): ParcelaPagamento
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

            $parcela->pagamento?->recalcularStatus();

            return $parcela->fresh();
        });
    }
}
