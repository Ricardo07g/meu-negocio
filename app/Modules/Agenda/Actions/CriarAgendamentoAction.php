<?php

namespace App\Modules\Agenda\Actions;

use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Enums\StatusAgendamento;
use App\Exceptions\ConflitoAgendamentoException;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;

class CriarAgendamentoAction
{
    public function executar(AgendamentoData $data): Agendamento
    {
        // Calcular fim automaticamente se nao informado
        $fim = $data->fim;
        if (!$fim) {
            $servico = Servico::findOrFail($data->servico_id);
            $fim = $data->inicio->copy()->addMinutes($servico->duracao);
        }

        // Verificar conflito de horario
        $this->verificarConflito($data->atendente_id, $data->inicio, $fim);

        return Agendamento::create([
            'cliente_id' => $data->cliente_id,
            'servico_id' => $data->servico_id,
            'atendente_id' => $data->atendente_id,
            'inicio' => $data->inicio,
            'fim' => $fim,
            'status' => StatusAgendamento::Agendado,
        ]);
    }

    private function verificarConflito(int $atendenteId, $inicio, $fim, ?int $ignorarId = null): void
    {
        $query = Agendamento::where('atendente_id', $atendenteId)
            ->whereNotIn('status', [StatusAgendamento::Cancelado->value])
            ->where(function ($q) use ($inicio, $fim) {
                $q->where(function ($q2) use ($inicio, $fim) {
                    $q2->where('inicio', '<', $fim)
                        ->where('fim', '>', $inicio);
                });
            });

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        if ($query->exists()) {
            throw new ConflitoAgendamentoException();
        }
    }
}
