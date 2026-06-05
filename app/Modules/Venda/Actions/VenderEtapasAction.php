<?php

namespace App\Modules\Venda\Actions;

use App\Enums\StatusAgendamento;
use App\Enums\StatusVendaEtapas;
use App\Exceptions\ConflitoAgendamentoException;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;
use App\Modules\Venda\DTOs\VenderEtapasData;
use App\Modules\Venda\Models\VendaEtapas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VenderEtapasAction
{
    public function executar(VenderEtapasData $data): VendaEtapas
    {
        return DB::transaction(function () use ($data) {
            $servico = Servico::findOrFail($data->servico_id);

            $venda = VendaEtapas::create([
                'cliente_id' => $data->cliente_id,
                'servico_id' => $data->servico_id,
                'atendente_id' => $data->atendente_id,
                'valor_total' => $data->valor_total,
                'qtd_etapas' => count($data->datas),
                'status' => StatusVendaEtapas::Ativo,
            ]);

            $conflitos = [];

            foreach ($data->datas as $index => $dataStr) {
                $horarioSessao = $data->horarios[$index] ?? $data->horario;
                $inicio = Carbon::parse($dataStr.' '.$horarioSessao);
                $fim = $inicio->copy()->addMinutes($servico->duracao);

                // Verificar conflito
                $temConflito = Agendamento::where('atendente_id', $data->atendente_id)
                    ->whereNotIn('status', [StatusAgendamento::Cancelado->value])
                    ->where('inicio', '<', $fim)
                    ->where('fim', '>', $inicio)
                    ->exists();

                if ($temConflito) {
                    $conflitos[] = $inicio->format('d/m/Y H:i');

                    continue;
                }

                Agendamento::create([
                    'cliente_id' => $data->cliente_id,
                    'servico_id' => $data->servico_id,
                    'atendente_id' => $data->atendente_id,
                    'venda_etapas_id' => $venda->id,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'status' => StatusAgendamento::Agendado,
                ]);
            }

            if (! empty($conflitos)) {
                throw new ConflitoAgendamentoException(
                    'Conflito de horario nas datas: '.implode(', ', $conflitos)
                );
            }

            return $venda;
        });
    }
}
