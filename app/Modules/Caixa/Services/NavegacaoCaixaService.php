<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Services;

use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Para onde as setas do Caixa Diário levam (leitura).
 *
 * Andar dia a dia obrigava a clicar por dias vazios sem saber onde havia algo.
 * A seta salta para o dia anterior/próximo com MOVIMENTO — e movimento não é só
 * "tem caixa": venda no cartão ou pix registra a baixa sem caixa aberto
 * (ADR-0011), e a tela mostra esse dia normalmente (ADR-0014). Saltar só entre
 * caixas esconderia justamente esses dias.
 *
 * Dia com movimento = união de: caixa (aberto ou fechado), recebimento
 * (`BaixaPagamento.data`), estorno (`BaixaPagamento.estornado_em`) e despesa paga
 * (`BaixaDespesa.data`). Sangria e reforço não entram: só existem com caixa.
 *
 * Tenancy pelos global scopes (RedeTrait + EmpresaTrait): a tela do Caixa já
 * resolveu a unidade única antes de chamar, então a seta nunca para num dia que
 * só tem movimento de outra unidade.
 */
class NavegacaoCaixaService
{
    /** Dia com movimento mais recente antes de `$dia`, ou null se não houver. */
    public function anterior(string $dia): ?string
    {
        $datas = $this->extremos(fn (Builder $q, string $col) => $q->whereDate($col, '<', $dia)->max($col));

        return $datas ? max($datas) : null;
    }

    /**
     * Próximo dia com movimento depois de `$dia`. Sem nenhum e com `$dia` no
     * passado, cai em hoje — é onde se abre o caixa do dia, e a seta não pode
     * deixar o usuário preso num caixa antigo. Em hoje ou depois, null.
     */
    public function proximo(string $dia): ?string
    {
        $hoje = today()->toDateString();

        if ($dia >= $hoje) {
            return null;
        }

        $datas = $this->extremos(fn (Builder $q, string $col) => $q
            ->whereDate($col, '>', $dia)
            ->whereDate($col, '<=', $hoje)
            ->min($col));

        return $datas ? min($datas) : $hoje;
    }

    /**
     * Aplica a busca a cada fonte de movimento e devolve os dias achados
     * (`Y-m-d`). `max()`/`min()` devolvem a coluna crua — date ou datetime,
     * conforme o driver —, por isso a normalização.
     *
     * @param  \Closure(Builder<Caixa>|Builder<BaixaPagamento>|Builder<BaixaDespesa>, string): mixed  $busca
     * @return list<string>
     */
    private function extremos(\Closure $busca): array
    {
        $fontes = [
            [Caixa::query(), 'data'],
            [BaixaPagamento::query(), 'data'],
            [BaixaPagamento::query()->whereNotNull('estornado_em'), 'estornado_em'],
            [BaixaDespesa::query(), 'data'],
        ];

        $dias = [];
        foreach ($fontes as [$query, $coluna]) {
            $valor = $busca($query, $coluna);
            if ($valor !== null) {
                $dias[] = Carbon::parse((string) $valor)->toDateString();
            }
        }

        return $dias;
    }
}
