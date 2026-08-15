<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\{StatusAgendamento, StatusPagamento};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Services\CaixaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelarAgendamentoAction
{
    public function __construct(private CaixaService $caixaService) {}

    /**
     * Cancela o atendimento e desfaz o dinheiro que ele gerou.
     *
     * Antes, o estorno aqui era um `update(['status' => Estornado])` na mão: o
     * título mudava de rótulo, mas o dinheiro continuava na gaveta (nenhum
     * contra-lançamento) e as parcelas a receber sobreviviam ao cancelamento.
     * Cancelar venda e cancelar agendamento são o mesmo evento financeiro, então
     * passam pelo mesmo caminho — `CaixaService::estornarPagamento`, que gera o
     * contra-lançamento por baixa, marca `estornado_em` e recusa estorno em caixa
     * fechado (ADR-0011).
     */
    public function executar(Agendamento $agendamento): Agendamento
    {
        if ($agendamento->status === StatusAgendamento::Finalizado) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível cancelar um agendamento já finalizado.',
            ]);
        }

        return DB::transaction(function () use ($agendamento) {
            $agendamento->update(['status' => StatusAgendamento::Cancelado]);

            $pagamento = $agendamento->load('pagamento')->pagamento;
            $jaDesfeito = $pagamento && in_array(
                $pagamento->status,
                [StatusPagamento::Estornado, StatusPagamento::Cancelado],
                true,
            );

            // Estorno não é idempotente (cada chamada gera contra-lançamento),
            // então título já desfeito não passa por aqui de novo.
            if ($pagamento && ! $jaDesfeito) {
                $this->caixaService->estornarPagamento($pagamento);
            }

            return $agendamento->fresh();
        });
    }
}
