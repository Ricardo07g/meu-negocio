<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Services;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\{Empresa, HorarioAtendimento};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O expediente da unidade: quando a agenda aceita atendimento.
 *
 * Fonte única da janela — consumida pela validação da agenda (que recusa
 * horário fora dela) e pelo calendário (que desenha só as horas úteis).
 */
class ExpedienteService
{
    /** Toda unidade nasce operante: contas, formas de pagamento e expediente. */
    public function semearPadrao(int $redeId, int $empresaId): void
    {
        foreach (HorarioAtendimento::PADRAO as $dia => [$inicio, $fim, $ativo]) {
            HorarioAtendimento::create([
                'rede_id' => $redeId,
                'empresa_id' => $empresaId,
                'usuario_id' => null,
                'dia_semana' => $dia,
                'hora_inicio' => $inicio,
                'hora_fim' => $fim,
                'ativo' => $ativo,
            ]);
        }
    }

    /**
     * A unidade tem expediente configurado?
     *
     * Rede de segurança do validador: sem nenhuma linha, a agenda não restringe
     * horário nenhum. Recusar tudo seria pior que não validar — uma unidade cujo
     * expediente sumiu (dado antigo, apagado à mão) ficaria com a agenda
     * inutilizável, e o operador não teria como nem agendar para consertar.
     */
    public function configurado(int $empresaId): bool
    {
        return HorarioAtendimento::where('empresa_id', $empresaId)->exists();
    }

    /**
     * Expediente da unidade (as 7 linhas), indexado por dia da semana.
     *
     * @return Collection<int, HorarioAtendimento>
     */
    public function daEmpresa(int $empresaId): Collection
    {
        /** @var Collection<int, HorarioAtendimento> $linhas */
        $linhas = HorarioAtendimento::where('empresa_id', $empresaId)
            ->daEmpresaToda()
            ->orderBy('dia_semana')
            ->get();

        return $linhas;
    }

    /**
     * Janela vigente para um atendente num dia.
     *
     * A linha do atendente vence a da empresa quando existe — é o gancho do
     * horário por profissional (a v1 só expõe a UI da empresa, mas quem
     * consulta já não precisa mudar quando ela chegar).
     *
     * @return Collection<int, HorarioAtendimento>
     */
    public function faixasDoDia(int $empresaId, int $atendenteId, int $diaSemana): Collection
    {
        $doAtendente = HorarioAtendimento::where('empresa_id', $empresaId)
            ->where('usuario_id', $atendenteId)
            ->where('dia_semana', $diaSemana)
            ->ativos()
            ->get();

        if ($doAtendente->isNotEmpty()) {
            return $doAtendente;
        }

        return HorarioAtendimento::where('empresa_id', $empresaId)
            ->daEmpresaToda()
            ->where('dia_semana', $diaSemana)
            ->ativos()
            ->get();
    }

    /**
     * Janela de visualização do calendário: a primeira e a última hora úteis
     * da semana. Sem expediente configurado, cai no comercial padrão — melhor
     * que uma grade vazia.
     *
     * @return array{0: int, 1: int}
     */
    public function janelaDoCalendario(int $empresaId): array
    {
        $linhas = $this->daEmpresa($empresaId)->where('ativo', true);

        if ($linhas->isEmpty()) {
            return [8, 18];
        }

        $inicio = (int) $linhas->map(fn (HorarioAtendimento $h) => (int) substr($h->hora_inicio, 0, 2))->min();
        $fim = (int) $linhas->map(fn (HorarioAtendimento $h) => (int) ceil((float) substr($h->hora_fim, 0, 2) + (substr($h->hora_fim, 3, 2) === '00' ? 0 : 1)))->max();

        // Uma hora de folga de cada lado deixa o encaixe visível na grade em vez
        // de escondido fora dela.
        return [max(0, $inicio - 1), min(24, $fim + 1)];
    }

    /**
     * Substitui o expediente da unidade pelo informado na tela.
     *
     * Troca em bloco (apaga e recria) em vez de casar linha a linha: são 7
     * linhas, e reconciliar por id abriria espaço para o formulário deixar
     * órfãos de dias que sumiram.
     *
     * @param  array<int, array{ativo?: mixed, hora_inicio?: string|null, hora_fim?: string|null}>  $dias
     */
    public function salvar(Empresa $empresa, array $dias): void
    {
        DB::transaction(function () use ($empresa, $dias) {
            HorarioAtendimento::where('empresa_id', $empresa->id)->daEmpresaToda()->delete();

            foreach (HorarioAtendimento::PADRAO as $dia => [$inicioPadrao, $fimPadrao]) {
                $informado = $dias[$dia] ?? [];
                $ativo = (bool) ($informado['ativo'] ?? false);
                $inicio = $informado['hora_inicio'] ?: $inicioPadrao;
                $fim = $informado['hora_fim'] ?: $fimPadrao;

                if ($ativo && $fim <= $inicio) {
                    throw new NegocioException(
                        sprintf('%s: o fim do expediente precisa ser depois do início.', HorarioAtendimento::DIAS[$dia])
                    );
                }

                HorarioAtendimento::create([
                    'rede_id' => $empresa->rede_id,
                    'empresa_id' => $empresa->id,
                    'usuario_id' => null,
                    'dia_semana' => $dia,
                    'hora_inicio' => $inicio,
                    'hora_fim' => $fim,
                    'ativo' => $ativo,
                ]);
            }
        });
    }

    /** Resumo legível ("Seg–Sex 08:00–18:00 · Sáb 08:00–12:00") para exibir na agenda. */
    public function resumo(int $empresaId): string
    {
        $ativos = $this->daEmpresa($empresaId)->where('ativo', true);

        if ($ativos->isEmpty()) {
            return 'Sem expediente configurado';
        }

        return $ativos
            ->map(fn (HorarioAtendimento $h) => sprintf(
                '%s %s–%s',
                // mb_substr: "Sábado" tem acento, e cortar por bytes devolvia "Sá".
                mb_substr($h->nomeDoDia(), 0, 3),
                substr($h->hora_inicio, 0, 5),
                substr($h->hora_fim, 0, 5),
            ))
            ->implode(' · ');
    }
}
