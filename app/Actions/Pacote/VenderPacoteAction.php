<?php

namespace App\Actions\Pacote;

use App\DTO\Pacote\VenderPacoteData;
use App\Enums\StatusAgendamento;
use App\Enums\StatusVendaPacote;
use App\Exceptions\ConflitoAgendamentoException;
use App\Models\Agendamento;
use App\Models\Servico;
use App\Models\VendaPacote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VenderPacoteAction
{
    public function executar(VenderPacoteData $data): VendaPacote
    {
        return DB::transaction(function () use ($data) {
            $servico = Servico::findOrFail($data->servico_id);

            $venda = VendaPacote::create([
                'cliente_id' => $data->cliente_id,
                'servico_id' => $data->servico_id,
                'profissional_id' => $data->profissional_id,
                'valor_total' => $data->valor_total,
                'qtd_sessoes' => count($data->datas),
                'status' => StatusVendaPacote::Ativo,
            ]);

            $conflitos = [];

            foreach ($data->datas as $index => $dataStr) {
                $inicio = Carbon::parse($dataStr . ' ' . $data->horario);
                $fim = $inicio->copy()->addMinutes($servico->duracao);

                // Verificar conflito
                $temConflito = Agendamento::where('profissional_id', $data->profissional_id)
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
                    'profissional_id' => $data->profissional_id,
                    'venda_pacote_id' => $venda->id,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'status' => StatusAgendamento::Agendado,
                ]);
            }

            if (!empty($conflitos)) {
                throw new ConflitoAgendamentoException(
                    'Conflito de horario nas datas: ' . implode(', ', $conflitos)
                );
            }

            return $venda;
        });
    }
}
