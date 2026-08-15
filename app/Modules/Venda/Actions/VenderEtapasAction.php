<?php

declare(strict_types=1);

namespace App\Modules\Venda\Actions;

use App\Enums\{StatusAgendamento, StatusVendaEtapas};
use App\Exceptions\{ConflitoAgendamentoException, ForaDoExpedienteException};
use App\Modules\Agenda\Actions\VerificarDisponibilidadeAction;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Servico\Models\Servico;
use App\Modules\Venda\DTOs\VenderEtapasData;
use App\Modules\Venda\Models\VendaEtapas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VenderEtapasAction
{
    public function __construct(private VerificarDisponibilidadeAction $verificarDisponibilidade) {}

    /**
     * @param  bool  $forcarHorario  encaixe fora do expediente (exige permissão no caller)
     */
    public function executar(VenderEtapasData $data, bool $forcarHorario = false): VendaEtapas
    {
        return DB::transaction(function () use ($data, $forcarHorario) {
            $servico = Servico::findOrFail($data->servico_id);

            $venda = VendaEtapas::create([
                'cliente_id' => $data->cliente_id,
                'servico_id' => $data->servico_id,
                'atendente_id' => $data->atendente_id,
                // Data representativa da venda = primeira sessao (coluna NOT NULL,
                // exibida no _venda_card). As datas de cada etapa ficam nos agendamentos.
                'data' => Carbon::parse($data->datas[0]),
                'valor_total' => $data->valor_total,
                'qtd_etapas' => count($data->datas),
                'status' => StatusVendaEtapas::Ativo,
            ]);

            $conflitos = [];
            $foraDoExpediente = [];

            foreach ($data->datas as $index => $dataStr) {
                $horarioSessao = $data->horarios[$index] ?? $data->horario;
                $inicio = Carbon::parse($dataStr.' '.$horarioSessao);
                $fim = $inicio->copy()->addMinutes($servico->duracao);
                $quando = $inicio->format('d/m/Y').' às '.$inicio->format('H:i');

                // As sessões são avaliadas em bloco: a venda de 10 etapas informa
                // TODAS as datas problemáticas de uma vez, em vez de reprovar uma
                // por vez e obrigar o vendedor a descobrir o resto por tentativa.
                try {
                    $encaixe = $this->verificarDisponibilidade->executar(
                        empresaId: (int) $venda->empresa_id,
                        atendenteId: (int) $data->atendente_id,
                        inicio: $inicio,
                        fim: $fim,
                        forcarHorario: $forcarHorario,
                    );
                } catch (ConflitoAgendamentoException) {
                    $conflitos[] = $quando;

                    continue;
                } catch (ForaDoExpedienteException) {
                    $foraDoExpediente[] = $quando;

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
                    'fora_expediente' => $encaixe,
                ]);
            }

            if (! empty($conflitos)) {
                $profissional = $venda->atendente->nome;

                throw new ConflitoAgendamentoException(sprintf(
                    '%s já está com a agenda ocupada em: %s. Ajuste a data ou o horário dessas sessões para concluir a venda.',
                    $profissional,
                    implode('; ', $conflitos),
                ));
            }

            if (! empty($foraDoExpediente)) {
                throw new ForaDoExpedienteException(sprintf(
                    'Fora do expediente da unidade em: %s. Ajuste essas sessões ou peça a quem pode autorizar o encaixe.',
                    implode('; ', $foraDoExpediente),
                ));
            }

            return $venda;
        });
    }
}
