<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Actions;

use App\Enums\StatusAgendamento;
use App\Exceptions\{ConflitoAgendamentoException, ForaDoExpedienteException};
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\HorarioAtendimento;
use App\Modules\Tenant\Services\ExpedienteService;
use Carbon\Carbon;

/**
 * As duas perguntas que todo horário da agenda tem de responder: **o atendente
 * está livre?** e **a unidade está aberta?**
 *
 * Existe como peça única porque as portas divergiam. A verificação de conflito
 * morava privada dentro de `CriarAgendamentoAction`, então o `reagendar` — que
 * move início e fim por drag-and-drop — não checava nada: dava para empilhar
 * dois clientes no mesmo atendente arrastando um evento por cima do outro. E o
 * expediente não era verificado em lugar nenhum, porque não existia.
 *
 * As duas respostas não têm o mesmo peso:
 *
 * - **conflito é "não"**: dois clientes no mesmo atendente no mesmo horário não
 *   é exceção, é erro. Não há como forçar.
 * - **fora do expediente é "quer mesmo?"**: encaixar cliente às 19h acontece
 *   todo dia na vida real. Vira decisão consciente — exige permissão e fica
 *   marcada no registro.
 */
class VerificarDisponibilidadeAction
{
    public function __construct(private ExpedienteService $expediente) {}

    /**
     * @param  bool  $forcarHorario  quem tem `agendamento.forcar_horario` pode encaixar
     * @return bool se o horário está fora do expediente (o encaixe a registrar)
     */
    public function executar(
        int $empresaId,
        int $atendenteId,
        Carbon $inicio,
        Carbon $fim,
        ?int $ignorarId = null,
        bool $forcarHorario = false,
    ): bool {
        $this->verificarConflito($atendenteId, $inicio, $fim, $ignorarId);

        $fora = ! $this->dentroDoExpediente($empresaId, $atendenteId, $inicio, $fim);

        if ($fora && ! $forcarHorario) {
            throw new ForaDoExpedienteException($this->explicar($empresaId, $atendenteId, $inicio));
        }

        return $fora;
    }

    /**
     * Sobreposição com outro atendimento do mesmo atendente (cancelado não conta).
     */
    private function verificarConflito(int $atendenteId, Carbon $inicio, Carbon $fim, ?int $ignorarId): void
    {
        $query = Agendamento::where('atendente_id', $atendenteId)
            ->whereNotIn('status', [StatusAgendamento::Cancelado->value])
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        if ($query->exists()) {
            throw new ConflitoAgendamentoException;
        }
    }

    private function dentroDoExpediente(int $empresaId, int $atendenteId, Carbon $inicio, Carbon $fim): bool
    {
        // Unidade sem expediente configurado não restringe: a regra que ninguém
        // definiu não pode trancar a agenda de quem depende dela.
        if (! $this->expediente->configurado($empresaId)) {
            return true;
        }

        // Atendimento que vira o dia não cabe em nenhuma faixa: é encaixe por definição.
        if ($inicio->toDateString() !== $fim->toDateString()) {
            return false;
        }

        return $this->expediente
            ->faixasDoDia($empresaId, $atendenteId, $inicio->dayOfWeek)
            ->contains(fn (HorarioAtendimento $faixa) => $faixa->contem($inicio) && $faixa->contem($fim));
    }

    /** Mensagem que diz qual é a janela, não só que o horário não serve. */
    private function explicar(int $empresaId, int $atendenteId, Carbon $inicio): string
    {
        $dia = HorarioAtendimento::DIAS[$inicio->dayOfWeek] ?? 'este dia';
        $faixas = $this->expediente->faixasDoDia($empresaId, $atendenteId, $inicio->dayOfWeek);

        if ($faixas->isEmpty()) {
            return sprintf('Fora do expediente: não há atendimento em %s.', mb_strtolower($dia));
        }

        $janela = $faixas
            ->map(fn (HorarioAtendimento $f) => substr($f->hora_inicio, 0, 5).'–'.substr($f->hora_fim, 0, 5))
            ->implode(', ');

        return sprintf('Fora do expediente: %s atende das %s.', mb_strtolower($dia), $janela);
    }
}
