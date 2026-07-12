<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Services;

use App\Enums\{FormaPagamento, StatusCaixa, StatusPagamento, StatusParcela, TipoMovimentoCaixa};
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa, MovimentoCaixa};
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use Illuminate\Support\Facades\DB;

class CaixaService
{
    public function caixaDoDia(string $data): ?Caixa
    {
        return Caixa::where('data', $data)->first();
    }

    /**
     * Caixa aberto de uma empresa especifica (opcionalmente de uma data).
     *
     * Usado por baixa/estorno para NAO depender do contexto de empresa
     * ambiente (empresas_atuais/empresa_contexto_atual): a operacao acontece
     * sobre a empresa da propria parcela/baixa (ja autorizada). Por isso
     * removemos o global scope 'empresa' e filtramos empresa_id na mao — o
     * scope de rede (RedeTrait) continua ativo, entao nunca cruza redes.
     */
    public function caixaAbertoDaEmpresa(int $empresaId, ?string $data = null): ?Caixa
    {
        $query = Caixa::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresaId)
            ->where('status', StatusCaixa::Aberto);

        if ($data !== null) {
            $query->whereDate('data', $data);
        }

        return $query->first();
    }

    public function abrir(float $saldoAbertura, string $data, ?string $observacao = null): Caixa
    {
        if ($this->caixaDoDia($data)) {
            throw new NegocioException('Já existe um caixa para esta data.');
        }

        return Caixa::create([
            'usuario_id' => auth()->id(),
            'data' => $data,
            'saldo_abertura' => $saldoAbertura,
            'status' => StatusCaixa::Aberto,
            'observacao' => $observacao,
        ]);
    }

    public function fechar(Caixa $caixa, float $saldoFechamento, ?string $observacao = null): Caixa
    {
        $caixa->update([
            'saldo_fechamento' => $saldoFechamento,
            'status' => StatusCaixa::Fechado,
            'fechado_em' => now(),
            'fechado_por' => auth()->id(),
            'observacao' => $observacao ?? $caixa->observacao,
        ]);

        return $caixa->fresh();
    }

    public function reabrir(Caixa $caixa, string $motivo): Caixa
    {
        if ($caixa->status !== StatusCaixa::Fechado) {
            throw new NegocioException('Somente caixas fechados podem ser reabertos.');
        }

        $registro = sprintf(
            '[Reaberto em %s por %s] %s',
            now()->format('d/m/Y H:i'),
            auth()->user()->nome ?? 'Usuário',
            $motivo,
        );

        $observacao = $caixa->observacao
            ? $caixa->observacao."\n".$registro
            : $registro;

        $caixa->update([
            'status' => StatusCaixa::Aberto,
            'saldo_fechamento' => null,
            'fechado_em' => null,
            'fechado_por' => null,
            'observacao' => $observacao,
        ]);

        activity()
            ->performedOn($caixa)
            ->causedBy(auth()->user())
            ->withProperties(['motivo' => $motivo])
            ->log('Caixa reaberto');

        return $caixa->fresh();
    }

    public function registrarSangria(Caixa $caixa, float $valor, string $descricao): MovimentoCaixa
    {
        return MovimentoCaixa::create([
            'caixa_id' => $caixa->id,
            'tipo' => TipoMovimentoCaixa::Sangria,
            'valor' => $valor,
            'descricao' => $descricao,
        ]);
    }

    public function registrarReforco(Caixa $caixa, float $valor, string $descricao): MovimentoCaixa
    {
        return MovimentoCaixa::create([
            'caixa_id' => $caixa->id,
            'tipo' => TipoMovimentoCaixa::Reforco,
            'valor' => $valor,
            'descricao' => $descricao,
        ]);
    }

    /**
     * Baixa uma parcela de Pagamento (contas a receber).
     * Atualiza a parcela, cria BaixaPagamento + MovimentoCaixa de entrada,
     * e recalcula o status do título pai.
     */
    public function darBaixaParcelaPagamento(
        ParcelaPagamento $parcela,
        float $valor,
        FormaPagamento $formaPagamento,
        ?string $observacao = null,
        float $multa = 0,
        float $juros = 0,
        float $desconto = 0,
    ): BaixaPagamento {
        return $this->aplicarBaixaParcela(
            parcela: $parcela,
            valor: $valor,
            formaPagamento: $formaPagamento,
            observacao: $observacao,
            multa: $multa,
            juros: $juros,
            desconto: $desconto,
            baixaClass: BaixaPagamento::class,
            parcelaFk: 'parcela_pagamento_id',
            tipoMovimento: TipoMovimentoCaixa::Entrada,
            movimentoFk: 'baixa_pagamento_id',
            tituloLabel: 'do pagamento',
            tituloId: $parcela->pagamento_id,
            mensagemSemCaixa: 'É necessário um caixa aberto de hoje desta empresa para registrar a baixa.',
            mensagemLiquidoInvalido: 'O total líquido do recebimento precisa ser positivo.',
            recalcularTitulo: fn () => $parcela->pagamento?->recalcularStatus(),
        );
    }

    /**
     * Baixa uma parcela de Despesa (contas a pagar).
     * Saída do caixa + atualiza status da parcela e do título.
     */
    public function darBaixaParcelaDespesa(
        ParcelaDespesa $parcela,
        float $valor,
        FormaPagamento $formaPagamento,
        ?string $observacao = null,
        float $multa = 0,
        float $juros = 0,
        float $desconto = 0,
    ): BaixaDespesa {
        return $this->aplicarBaixaParcela(
            parcela: $parcela,
            valor: $valor,
            formaPagamento: $formaPagamento,
            observacao: $observacao,
            multa: $multa,
            juros: $juros,
            desconto: $desconto,
            baixaClass: BaixaDespesa::class,
            parcelaFk: 'parcela_despesa_id',
            tipoMovimento: TipoMovimentoCaixa::Saida,
            movimentoFk: 'baixa_despesa_id',
            tituloLabel: 'da despesa',
            tituloId: $parcela->despesa_id,
            mensagemSemCaixa: 'É necessário um caixa aberto de hoje desta empresa para registrar o pagamento.',
            mensagemLiquidoInvalido: 'O total líquido do pagamento precisa ser positivo.',
            recalcularTitulo: fn () => $parcela->despesa?->recalcularStatus(),
        );
    }

    /**
     * Template de baixa por parcela (recebimento ou pagamento).
     * Valida, exige caixa aberto, cria Baixa + MovimentoCaixa, atualiza parcela
     * e recalcula status do titulo.
     *
     * @param  ParcelaPagamento|ParcelaDespesa  $parcela
     * @param  class-string<BaixaPagamento|BaixaDespesa>  $baixaClass
     * @param  \Closure():void  $recalcularTitulo
     * @return BaixaPagamento|BaixaDespesa
     */
    private function aplicarBaixaParcela(
        $parcela,
        float $valor,
        FormaPagamento $formaPagamento,
        ?string $observacao,
        float $multa,
        float $juros,
        float $desconto,
        string $baixaClass,
        string $parcelaFk,
        TipoMovimentoCaixa $tipoMovimento,
        string $movimentoFk,
        string $tituloLabel,
        int $tituloId,
        string $mensagemSemCaixa,
        string $mensagemLiquidoInvalido,
        \Closure $recalcularTitulo,
    ) {
        return DB::transaction(function () use (
            $parcela, $valor, $formaPagamento, $observacao, $multa, $juros, $desconto,
            $baixaClass, $parcelaFk, $tipoMovimento, $movimentoFk,
            $tituloLabel, $tituloId, $mensagemSemCaixa, $mensagemLiquidoInvalido, $recalcularTitulo,
        ) {
            if ($multa < 0 || $juros < 0 || $desconto < 0) {
                throw new NegocioException('Multa, juros e desconto não podem ser negativos.');
            }

            $saldo = $parcela->saldoRestante();
            if ($valor > $saldo + 0.001) {
                throw new NegocioException('O valor principal excede o saldo restante da parcela.');
            }
            if ($desconto > $valor + 0.001) {
                throw new NegocioException('O desconto não pode ser maior que o valor principal.');
            }
            if (($valor + $multa + $juros - $desconto) <= 0) {
                throw new NegocioException($mensagemLiquidoInvalido);
            }

            $caixa = $this->caixaAbertoDaEmpresa($parcela->empresa_id, now()->toDateString());
            if (! $caixa) {
                throw new NegocioException($mensagemSemCaixa);
            }

            $baixa = $baixaClass::create([
                $parcelaFk => $parcela->id,
                'caixa_id' => $caixa->id,
                'valor' => $valor,
                'multa' => $multa,
                'juros' => $juros,
                'desconto' => $desconto,
                'forma_pagamento' => $formaPagamento,
                'data' => now(),
                'observacao' => $observacao,
            ]);

            $parcela->update([
                'valor_pago' => (float) $parcela->valor_pago + $valor,
                'forma_pagamento' => $formaPagamento,
            ]);

            $parcela->refresh();

            if ((float) $parcela->valor_pago + 0.001 >= (float) $parcela->valor) {
                $parcela->update(['status' => StatusParcela::Pago]);
            }

            $totalLiquido = $valor + $multa + $juros - $desconto;
            $descricao = "Parcela {$parcela->numero}/{$parcela->total} {$tituloLabel} #{$tituloId}";
            if ($multa > 0 || $juros > 0 || $desconto > 0) {
                $partes = ['principal R$ '.number_format($valor, 2, ',', '.')];
                if ($multa > 0) {
                    $partes[] = 'multa R$ '.number_format($multa, 2, ',', '.');
                }
                if ($juros > 0) {
                    $partes[] = 'juros R$ '.number_format($juros, 2, ',', '.');
                }
                if ($desconto > 0) {
                    $partes[] = 'desconto R$ '.number_format($desconto, 2, ',', '.');
                }
                $descricao .= ' ('.implode(' + ', $partes).')';
            }

            MovimentoCaixa::create([
                'caixa_id' => $caixa->id,
                'tipo' => $tipoMovimento,
                'valor' => $totalLiquido,
                'descricao' => $descricao,
                'forma_pagamento' => $formaPagamento,
                $movimentoFk => $baixa->id,
            ]);

            $recalcularTitulo();

            return $baixa;
        });
    }

    /**
     * Estorna um título de Pagamento (na prática: cancelamento da venda).
     * - Cada baixa recebida gera uma saída NO MESMO caixa em que entrou, pelo
     *   valor líquido real (principal + multa + juros − desconto), vinculada à
     *   baixa (rastro completo) — entrada e saída se anulam no caixa de origem.
     * - Parcelas pendentes: marcadas como canceladas.
     * - Título: status = estornado.
     *
     * Se o caixa de origem de alguma baixa estiver fechado, exige reabri-lo
     * antes (NegocioException). Sem isso o estorno alteraria o saldo de um dia
     * já fechado — inconsistente com o histórico contábil.
     */
    public function estornarPagamento(Pagamento $pagamento): void
    {
        DB::transaction(function () use ($pagamento) {
            $pagamento->load('parcelas.baixas');

            // Estorna cada baixa NO MESMO caixa em que ela entrou, pelo valor
            // liquido real recebido — assim entrada e saida se anulam no caixa
            // certo (empresa/dia de origem) e o rastro fica ligado a baixa.
            foreach ($pagamento->parcelas as $parcela) {
                foreach ($parcela->baixas as $baixa) {
                    // Busca o caixa de origem sem depender do contexto de empresa
                    // ambiente (mesma estrategia de caixaAbertoDaEmpresa): assim o
                    // guard de caixa fechado nunca e silenciosamente pulado.
                    $caixaOrigem = Caixa::withoutGlobalScope('empresa')->find($baixa->caixa_id);
                    if ($caixaOrigem && $caixaOrigem->status === StatusCaixa::Fechado) {
                        throw new NegocioException(
                            'Não é possível estornar: o caixa em que o recebimento entrou está fechado. '
                            .'Reabra o caixa da respectiva data antes de cancelar a venda.'
                        );
                    }

                    MovimentoCaixa::create([
                        'caixa_id' => $baixa->caixa_id,
                        'tipo' => TipoMovimentoCaixa::Saida,
                        'valor' => $baixa->valorTotal(),
                        'descricao' => "Estorno da parcela {$parcela->numero}/{$parcela->total} do pagamento #{$pagamento->id}",
                        'forma_pagamento' => $baixa->forma_pagamento,
                        'baixa_pagamento_id' => $baixa->id,
                    ]);
                }

                if ($parcela->status === StatusParcela::Pendente) {
                    $parcela->update(['status' => StatusParcela::Cancelado]);
                }
            }

            $pagamento->update(['status' => StatusPagamento::Estornado]);
        });
    }
}
