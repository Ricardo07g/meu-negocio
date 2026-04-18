<?php

namespace App\Modules\Caixa\Services;

use App\Enums\FormaPagamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusPagamento;
use App\Enums\TipoMovimentoCaixa;
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Pagamento\Models\Pagamento;
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

    public function registrarEntrada(
        Caixa $caixa,
        float $valor,
        string $descricao,
        ?string $formaPagamento = null,
        ?int $baixaPagamentoId = null,
    ): MovimentoCaixa {
        return MovimentoCaixa::create([
            'caixa_id' => $caixa->id,
            'tipo' => TipoMovimentoCaixa::Entrada,
            'valor' => $valor,
            'descricao' => $descricao,
            'forma_pagamento' => $formaPagamento,
            'baixa_pagamento_id' => $baixaPagamentoId,
        ]);
    }

    public function registrarSaida(
        Caixa $caixa,
        float $valor,
        string $descricao,
        ?int $despesaId = null,
    ): MovimentoCaixa {
        return MovimentoCaixa::create([
            'caixa_id' => $caixa->id,
            'tipo' => TipoMovimentoCaixa::Saida,
            'valor' => $valor,
            'descricao' => $descricao,
            'despesa_id' => $despesaId,
        ]);
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

    public function darBaixaPagamento(
        Pagamento $pagamento,
        float $valor,
        string $formaPagamento,
        ?string $observacao = null,
    ): BaixaPagamento {
        return DB::transaction(function () use ($pagamento, $valor, $formaPagamento, $observacao) {
            $saldoRestante = $pagamento->saldoRestante();

            if ($valor > $saldoRestante) {
                throw new NegocioException('O valor da baixa excede o saldo restante do pagamento.');
            }

            $caixa = $this->caixaAberto();

            $baixa = BaixaPagamento::create([
                'pagamento_id' => $pagamento->id,
                'caixa_id' => $caixa?->id,
                'valor' => $valor,
                'forma_pagamento' => $formaPagamento,
                'data' => now(),
                'observacao' => $observacao,
            ]);

            $pagamento->update([
                'valor_pago' => $pagamento->valor_pago + $valor,
            ]);

            $pagamento->refresh();

            if ($pagamento->valor_pago >= $pagamento->valor) {
                $pagamento->update([
                    'status' => StatusPagamento::Pago,
                ]);
            }

            if ($caixa) {
                $this->registrarEntrada(
                    $caixa,
                    $valor,
                    "Baixa de pagamento #{$pagamento->id}",
                    $formaPagamento,
                    $baixa->id,
                );
            }

            return $baixa;
        });
    }
}
