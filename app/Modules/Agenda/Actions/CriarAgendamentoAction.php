<?php

namespace App\Modules\Agenda\Actions;

use App\Modules\Agenda\DTOs\CriarAgendamentoData;
use App\Enums\StatusAgendamento;
use App\Exceptions\ConflitoAgendamentoException;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;

class CriarAgendamentoAction
{
    public function executar(CriarAgendamentoData $data): Agendamento
    {
        // Calcular fim automaticamente se nao informado
        $fim = $data->fim;
        if (!$fim) {
            $servico = Servico::findOrFail($data->servico_id);
            $fim = $data->inicio->copy()->addMinutes($servico->duracao);
        }

        // Verificar conflito de horario
        $this->verificarConflito($data->profissional_id, $data->inicio, $fim);

        return Agendamento::create([
            'cliente_id' => $data->cliente_id,
            'servico_id' => $data->servico_id,
            'profissional_id' => $data->profissional_id,
            'inicio' => $data->inicio,
            'fim' => $fim,
            'status' => StatusAgendamento::Agendado,
        ]);
    }

    private function verificarConflito(int $profissionalId, $inicio, $fim, ?int $ignorarId = null): void
    {
        $query = Agendamento::where('profissional_id', $profissionalId)
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
