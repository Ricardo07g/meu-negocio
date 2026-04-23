<?php

namespace App\Modules\Caixa\Services;

use App\Enums\FormaPagamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusParcela;
use App\Enums\StatusPagamento;
use App\Enums\TipoMovimentoCaixa;
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use Illuminate\Support\Facades\DB;

class CaixaService
{
    public function caixaAberto(): ?Caixa
    {
        return Caixa::where('status', StatusCaixa::Aberto)->first();
    }

    public function caixaDoDia(string $data): ?Caixa
    {
        return Caixa::where('data', $data)->first();
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
            ? $caixa->observacao . "\n" . $registro
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
            mensagemSemCaixa: 'É necessário um caixa aberto para registrar a baixa.',
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
            mensagemSemCaixa: 'É necessário um caixa aberto para registrar o pagamento.',
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

            $caixa = $this->caixaAberto();
            if (!$caixa) {
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
                $partes = ['principal R$ ' . number_format($valor, 2, ',', '.')];
                if ($multa > 0) $partes[] = 'multa R$ ' . number_format($multa, 2, ',', '.');
                if ($juros > 0) $partes[] = 'juros R$ ' . number_format($juros, 2, ',', '.');
                if ($desconto > 0) $partes[] = 'desconto R$ ' . number_format($desconto, 2, ',', '.');
                $descricao .= ' (' . implode(' + ', $partes) . ')';
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
     * - Parcelas pagas: permanecem marcadas como pagas (histórico), mas geram saída no caixa aberto.
     * - Parcelas pendentes: marcadas como canceladas.
     * - Título: status = estornado.
     *
     * Se o titulo ja tem valor recebido (parcelas pagas), exige caixa aberto
     * para registrar a saida. Sem essa validacao o saldo do caixa ficaria
     * inconsistente com o historico contabil.
     */
    public function estornarPagamento(Pagamento $pagamento): void
    {
        DB::transaction(function () use ($pagamento) {
            $pagamento->load('parcelas');

            $totalPago = (float) $pagamento->valorPago();

            if ($totalPago > 0 && !$this->caixaAberto()) {
                throw new NegocioException(
                    'Não é possível estornar um pagamento com valores recebidos sem caixa aberto. Abra o caixa antes de cancelar a venda.'
                );
            }

            foreach ($pagamento->parcelas as $parcela) {
                if ($parcela->status === StatusParcela::Pendente) {
                    $parcela->update(['status' => StatusParcela::Cancelado]);
                }
            }

            if ($totalPago > 0) {
                MovimentoCaixa::create([
                    'caixa_id' => $this->caixaAberto()->id,
                    'tipo' => TipoMovimentoCaixa::Saida,
                    'valor' => $totalPago,
                    'descricao' => "Estorno pagamento #{$pagamento->id}",
                ]);
            }

            $pagamento->update(['status' => StatusPagamento::Estornado]);
        });
    }
}
