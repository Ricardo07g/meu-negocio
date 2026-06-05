<?php

declare(strict_types=1);

namespace App\Modules\Pagamento\Actions;

use App\Enums\{StatusPagamento, StatusParcela};
use App\Exceptions\NegocioException;
use App\Modules\Pagamento\DTOs\CriarPagamentoData;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use App\Support\Parcelamento\CalculadoraParcelas;
use Illuminate\Support\Facades\DB;

/**
 * Cria um Pagamento (título) com suas parcelas.
 *
 * - À vista: 1 parcela, vencimento = hoje. Forma preenchida já.
 *            Baixa é responsabilidade do caller (CaixaService).
 * - Parcelado: N parcelas pendentes com vencimentos mensais.
 */
class CriarPagamentoComParcelasAction
{
    public function __construct(
        private CalculadoraParcelas $calculadora,
    ) {}

    public function executar(CriarPagamentoData $data): Pagamento
    {
        return DB::transaction(function () use ($data) {
            $this->validar($data);

            $pagamento = Pagamento::create([
                'cliente_id' => $data->cliente_id,
                'agendamento_id' => $data->agendamento_id,
                'venda_etapas_id' => $data->venda_etapas_id,
                'venda_produto_id' => $data->venda_produto_id,
                'valor_total' => $data->valor_total,
                'condicao_pagamento' => $data->condicao_pagamento,
                'forma_recebimento_prazo' => $data->condicao_pagamento->geraParcelas()
                    ? $data->forma_recebimento_prazo
                    : null,
                'mes_referencia' => $data->mes_referencia->copy()->startOfMonth(),
                'status' => StatusPagamento::Pendente,
                'descricao' => $data->descricao,
            ]);

            $parcelas = $this->montarParcelas($data);

            foreach ($parcelas as $p) {
                ParcelaPagamento::create([
                    'pagamento_id' => $pagamento->id,
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

            return $pagamento->fresh(['parcelas']);
        });
    }

    /**
     * Monta o array de parcelas: prefere as personalizadas do usuário;
     * se não veio, usa a calculadora padrão.
     */
    private function montarParcelas(CriarPagamentoData $data): array
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

        $primeiroVencimento = $data->primeiro_vencimento ?? now();

        $calculadas = $this->calculadora->calcular(
            (float) $data->valor_total,
            $numero,
            $primeiroVencimento,
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

    private function validar(CriarPagamentoData $data): void
    {
        if ($data->condicao_pagamento->exigeFormaNaCriacao() && ! $data->forma_pagamento_avista) {
            throw new NegocioException('Forma de pagamento é obrigatória.');
        }

        if ($data->condicao_pagamento->geraParcelas() && ! $data->forma_recebimento_prazo) {
            throw new NegocioException('Forma de recebimento prevista (carnê, etc) é obrigatória quando a venda é a prazo.');
        }

        if (! empty($data->parcelas_personalizadas)) {
            $somaValores = array_sum(array_map(
                fn ($p) => (float) $p['valor'],
                $data->parcelas_personalizadas,
            ));
            if (abs($somaValores - (float) $data->valor_total) > 0.01) {
                throw new NegocioException(
                    'A soma das parcelas não bate com o valor total da venda.'
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
            if (! $data->primeiro_vencimento) {
                throw new NegocioException('Primeiro vencimento é obrigatório para venda parcelada.');
            }
        }
    }
}
