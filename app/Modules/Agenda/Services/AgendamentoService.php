<?php

namespace App\Modules\Agenda\Services;

use App\Modules\Agenda\Actions\CancelarAgendamentoAction;
use App\Modules\Agenda\Actions\CriarAgendamentoAction;
use App\Modules\Agenda\Actions\FinalizarAgendamentoAction;
use App\Enums\StatusAgendamento;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AgendamentoService
{
    public function __construct(
        private CriarAgendamentoAction $criarAgendamento,
        private CancelarAgendamentoAction $cancelarAgendamento,
        private FinalizarAgendamentoAction $finalizarAgendamento,
    ) {}

    public function listar(): Collection
    {
        return Agendamento::with(['cliente', 'servico', 'atendente'])->get();
    }

    public function listarPorPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        return Agendamento::with(['cliente', 'servico', 'atendente'])
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

    public function atualizar(Agendamento $agendamento, AgendamentoData $data): Agendamento
    {
        $campos = array_filter($data->toArray(), fn ($v) => $v !== null);
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

    public function finalizar(Agendamento $agendamento): Agendamento
    {
        return $this->finalizarAgendamento->executar($agendamento);
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
