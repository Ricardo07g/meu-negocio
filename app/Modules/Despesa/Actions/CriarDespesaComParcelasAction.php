<?php

declare(strict_types=1);

namespace App\Modules\Despesa\Actions;

use App\Enums\{StatusDespesa, StatusParcela};
use App\Exceptions\NegocioException;
use App\Modules\Despesa\DTOs\CriarDespesaData;
use App\Modules\Despesa\Models\{Despesa, ParcelaDespesa};
use App\Support\Parcelamento\CalculadoraParcelas;
use Illuminate\Support\Facades\DB;

/**
 * Cria Despesa (título) + parcelas.
 *
 * - À vista: 1 parcela, vencimento conforme data_vencimento informada.
 *            Baixa efetiva é opcional; caller decide se marca como paga já.
 * - Parcelado: N parcelas pendentes com vencimentos mensais.
 */
class CriarDespesaComParcelasAction
{
    public function __construct(
        private CalculadoraParcelas $calculadora,
    ) {}

    public function executar(CriarDespesaData $data): Despesa
    {
        return DB::transaction(function () use ($data) {
            $this->validar($data);

            $despesa = Despesa::create([
                'categoria_despesa_id' => $data->categoria_despesa_id,
                'nome' => $data->nome,
                'fornecedor_nome' => $data->fornecedor_nome,
                'documento' => $data->documento,
                'observacoes' => $data->observacoes,
                'valor_total' => $data->valor_total,
                'condicao_pagamento' => $data->condicao_pagamento,
                'forma_recebimento_prazo' => $data->condicao_pagamento->geraParcelas()
                    ? $data->forma_recebimento_prazo
                    : null,
                'mes_referencia' => $data->mes_referencia->copy()->startOfMonth(),
                'data_emissao' => $data->data_emissao,
                'status' => StatusDespesa::Pendente,
            ]);

            $parcelas = $this->montarParcelas($data);

            foreach ($parcelas as $p) {
                ParcelaDespesa::create([
                    'despesa_id' => $despesa->id,
                    'numero' => $p['numero'],
                    'total' => $p['total'],
                    'valor' => $p['valor'],
                    'valor_pago' => 0,
                    'data_vencimento' => $p['data_vencimento'],
                    'mes_referencia' => $p['mes_referencia'],
                    'forma_pagamento' => $data->forma_pagamento_avista,
                    'status' => StatusParcela::Pendente,
                ]);
            }

            return $despesa->fresh(['parcelas']);
        });
    }

    /**
     * Monta o array de parcelas: prefere as personalizadas do usuário;
     * se não veio, usa a calculadora padrão.
     */
    private function montarParcelas(CriarDespesaData $data): array
    {
        if (! empty($data->parcelas_personalizadas)) {
            return array_map(function (array $p) use ($data) {
                return [
                    'numero' => (int) $p['numero'],
                    'total' => (int) $p['total'],
                    'valor' => (float) $p['valor'],
                    'data_vencimento' => $p['data_vencimento'],
                    'mes_referencia' => isset($p['mes_referencia'])
                        ? $p['mes_referencia']->copy()->startOfMonth()
                        : $data->mes_referencia->copy()->startOfMonth(),
                ];
            }, $data->parcelas_personalizadas);
        }

        $numero = $data->condicao_pagamento->geraParcelas()
            ? (int) $data->numero_parcelas
            : 1;

        $calculadas = $this->calculadora->calcular(
            (float) $data->valor_total,
            $numero,
            $data->primeiro_vencimento,
        );

        return array_map(function (array $p) use ($data) {
            return [
                'numero' => $p['numero'],
                'total' => $p['total'],
                'valor' => $p['valor'],
                'data_vencimento' => $p['data_vencimento'],
                'mes_referencia' => $data->mes_referencia->copy()->startOfMonth(),
            ];
        }, $calculadas);
    }

    private function validar(CriarDespesaData $data): void
    {
        if ($data->condicao_pagamento->exigeFormaNaCriacao() && ! $data->forma_pagamento_avista) {
            throw new NegocioException('Forma de pagamento é obrigatória.');
        }

        if ($data->condicao_pagamento->geraParcelas() && ! $data->forma_recebimento_prazo) {
            throw new NegocioException('Forma de recebimento prevista (carnê, etc) é obrigatória quando a despesa é a prazo.');
        }

        if (! empty($data->parcelas_personalizadas)) {
            $somaValores = array_sum(array_map(
                fn ($p) => (float) $p['valor'],
                $data->parcelas_personalizadas,
            ));
            if (abs($somaValores - (float) $data->valor_total) > 0.01) {
                throw new NegocioException(
                    'A soma das parcelas não bate com o valor total da despesa.'
                );
            }

            return;
        }

        if ($data->condicao_pagamento->geraParcelas()) {
            $n = (int) $data->numero_parcelas;
            if ($n < 2 || $n > CalculadoraParcelas::MAX_PARCELAS) {
                throw new NegocioException(
                    sprintf('Número de parcelas deve estar entre 2 e %d.', CalculadoraParcelas::MAX_PARCELAS)
                );
            }
        }
    }
}
