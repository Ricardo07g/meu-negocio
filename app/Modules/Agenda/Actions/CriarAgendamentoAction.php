<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\StatusAgendamento;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;
use App\Support\ContextoEmpresa;

class CriarAgendamentoAction
{
    public function __construct(private VerificarDisponibilidadeAction $verificarDisponibilidade) {}

    /**
     * @param  bool  $forcarHorario  encaixe fora do expediente (exige permissão no caller)
     */
    public function executar(AgendamentoData $data, bool $forcarHorario = false): Agendamento
    {
        // Calcular fim automaticamente se nao informado
        $fim = $data->fim;
        if (! $fim) {
            $servico = Servico::findOrFail($data->servico_id);
            $fim = $data->inicio->copy()->addMinutes($servico->duracao);
        }

        // A empresa do agendamento decide qual expediente vale. Resolver aqui —
        // e nao deixar so para o `EmpresaTrait::creating` — conserta de passagem
        // um caso que quebrava: Admin com varias empresas acessiveis e nenhuma
        // em contexto caia no ramo "deixa null" do trait, e o insert estourava
        // no NOT NULL de `empresa_id`. Validar o expediente de uma empresa e
        // gravar a linha em outra (ou em nenhuma) tambem seria incoerente.
        $empresaId = $data->empresa_id ?? ContextoEmpresa::resolver() ?? auth()->user()?->empresa_id;

        $foraExpediente = $this->verificarDisponibilidade->executar(
            empresaId: (int) $empresaId,
            atendenteId: (int) $data->atendente_id,
            inicio: $data->inicio,
            fim: $fim,
            forcarHorario: $forcarHorario,
        );

        return Agendamento::create(array_filter([
            'empresa_id' => $empresaId !== null ? (int) $empresaId : null,
            'cliente_id' => $data->cliente_id,
            'servico_id' => $data->servico_id,
            'atendente_id' => $data->atendente_id,
            'inicio' => $data->inicio,
            'fim' => $fim,
            'status' => StatusAgendamento::Agendado,
            'fora_expediente' => $foraExpediente,
        ], fn ($v) => $v !== null));
    }
}
