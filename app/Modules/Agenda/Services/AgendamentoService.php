<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Services;

use App\Enums\{MotivoSemCobranca, StatusAgendamento};
use App\Modules\Agenda\Actions\{CancelarAgendamentoAction, CriarAgendamentoAction, FinalizarAgendamentoAction, VerificarDisponibilidadeAction};
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AgendamentoService
{
    public function __construct(
        private CriarAgendamentoAction $criarAgendamento,
        private CancelarAgendamentoAction $cancelarAgendamento,
        private FinalizarAgendamentoAction $finalizarAgendamento,
        private VerificarDisponibilidadeAction $verificarDisponibilidade,
    ) {}

    public function listar(): Collection
    {
        return Agendamento::with(['cliente', 'servico', 'atendente'])->get();
    }

    /** @return Collection<int, Agendamento> */
    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        // `pagamento` alimenta a situacao financeira exibida no calendario.
        return Agendamento::with(['cliente', 'servico', 'atendente', 'pagamento'])
            ->where('inicio', '>=', $inicio)
            ->where('inicio', '<=', $fim)
            ->orderBy('inicio')
            ->get();
    }

    public function buscar(int $id): Agendamento
    {
        return Agendamento::with(['cliente', 'servico', 'atendente'])->findOrFail($id);
    }

    public function criar(AgendamentoData $data): Agendamento
    {
        return $this->criarAgendamento->executar($data);
    }

    /**
     * Reagenda pelo formulario completo.
     *
     * Passa pelo mesmo validador das outras portas (conflito + expediente) e
     * recalcula o fim quando o inicio muda: antes, mudar so o inicio deixava o
     * fim antigo no lugar — que podia acabar ANTES do novo inicio.
     */
    public function atualizar(Agendamento $agendamento, AgendamentoData $data, bool $forcarHorario = false): Agendamento
    {
        $campos = array_filter($data->toArray(), fn ($v) => $v !== null);

        $inicio = $data->inicio ?? $agendamento->inicio;
        $servicoId = $data->servico_id ?? $agendamento->servico_id;

        $fim = $data->fim ?? ($data->inicio
            ? $inicio->copy()->addMinutes(Servico::findOrFail($servicoId)->duracao)
            : $agendamento->fim);

        $campos['fim'] = $fim;
        $campos['fora_expediente'] = $this->verificarDisponibilidade->executar(
            empresaId: (int) $agendamento->empresa_id,
            atendenteId: (int) ($data->atendente_id ?? $agendamento->atendente_id),
            inicio: $inicio,
            fim: $fim,
            ignorarId: $agendamento->id,
            forcarHorario: $forcarHorario,
        );

        $agendamento->update($campos);

        return $agendamento->fresh();
    }

    public function cancelar(Agendamento $agendamento): Agendamento
    {
        return $this->cancelarAgendamento->executar($agendamento);
    }

    public function confirmar(Agendamento $agendamento): Agendamento
    {
        $agendamento->update(['status' => StatusAgendamento::Confirmado]);

        return $agendamento->fresh();
    }

    public function finalizar(Agendamento $agendamento, ?MotivoSemCobranca $motivoSemCobranca = null): Agendamento
    {
        return $this->finalizarAgendamento->executar($agendamento, $motivoSemCobranca);
    }

    public function listarPorData(Carbon $data): Collection
    {
        return Agendamento::with(['cliente', 'servico', 'atendente'])
            ->whereDate('inicio', $data)
            ->orderBy('inicio')
            ->get();
    }

    public function listarPorAtendente(int $atendenteId, Carbon $data): Collection
    {
        return Agendamento::with(['cliente', 'servico'])
            ->where('atendente_id', $atendenteId)
            ->whereDate('inicio', $data)
            ->orderBy('inicio')
            ->get();
    }
}
