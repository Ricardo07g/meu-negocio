<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Services;

use App\Enums\{StatusCaixa, StatusPagamento, StatusParcela, TipoConta, TipoLancamento};
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\{BaixaDespesa, BaixaPagamento, Caixa};
use App\Modules\Conta\Models\{Conta, Lancamento};
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use Illuminate\Support\Facades\DB;

class CaixaService
{
    public function caixaDoDia(string $data): ?Caixa
    {
        // whereDate (nao where('data', ...)) para casar independentemente de o driver
        // guardar a coluna date com ou sem hora — mesma estrategia de caixaAbertoDaEmpresa.
        return Caixa::whereDate('data', $data)->first();
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

    /**
     * Conta caixa padrao (a gaveta fisica) de uma empresa. Empresa-explicito
     * (mesma estrategia de caixaAbertoDaEmpresa: sem depender do contexto,
     * scope de rede preservado).
     */
    public function resolverContaCaixa(int $empresaId): Conta
    {
        $conta = $this->contaPorFlag($empresaId, 'eh_caixa_padrao');

        if (! $conta) {
            throw new NegocioException('Nenhuma conta caixa padrão configurada para esta empresa.');
        }

        return $conta;
    }

    /**
     * Resolve para onde o dinheiro de uma baixa vai, na empresa da transacao:
     *  1. a conta destino explicita da forma (se pertencer a essa empresa);
     *  2. senao, pela natureza — dinheiro/boleto/crediario caem na gaveta (conta
     *     caixa); recebivel (cartao/pix-maquineta) e pix-direto caem na conta
     *     banco/carteira (destino de recebivel), com fallback para a conta caixa.
     */
    public function resolverContaDestino(FormaPagamento $forma, int $empresaId): Conta
    {
        if ($forma->conta_destino_id) {
            $conta = Conta::withoutGlobalScope('empresa')
                ->where('empresa_id', $empresaId)
                ->find($forma->conta_destino_id);

            if ($conta) {
                return $conta;
            }
        }

        if (! $forma->gera_recebivel && $forma->tipo->destinoNaturalCaixa()) {
            return $this->resolverContaCaixa($empresaId);
        }

        return $this->contaPorFlag($empresaId, 'eh_destino_recebivel_padrao')
            ?? $this->resolverContaCaixa($empresaId);
    }

    /**
     * Se a baixa desta forma exige um caixa aberto: so quando o dinheiro entra
     * na gaveta na hora — imediato (nao gera recebivel) E conta destino do tipo
     * caixa. Usado na pre-validacao da venda a vista e no motor de baixa.
     */
    public function exigeCaixaAberto(FormaPagamento $forma, int $empresaId): bool
    {
        if ($forma->gera_recebivel) {
            return false;
        }

        return $this->resolverContaDestino($forma, $empresaId)->tipo === TipoConta::Caixa;
    }

    private function contaPorFlag(int $empresaId, string $flag): ?Conta
    {
        return Conta::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresaId)
            ->where($flag, true)
            ->first();
    }

    public function abrir(float $saldoAbertura, string $data, ?string $observacao = null): Caixa
    {
        if ($this->caixaDoDia($data)) {
            throw new NegocioException('Já existe um caixa para esta data.');
        }

        $caixa = Caixa::create([
            'usuario_id' => auth()->id(),
            'data' => $data,
            'saldo_abertura' => $saldoAbertura,
            'status' => StatusCaixa::Aberto,
            'observacao' => $observacao,
        ]);

        // Liga a sessao diaria a conta-caixa padrao da empresa (o razao da gaveta).
        $caixa->update(['conta_id' => $this->resolverContaCaixa((int) $caixa->empresa_id)->id]);

        return $caixa;
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

    public function registrarSangria(Caixa $caixa, float $valor, string $descricao): Lancamento
    {
        return Lancamento::create([
            'rede_id' => (int) $caixa->rede_id,
            'empresa_id' => (int) $caixa->empresa_id,
            'conta_id' => $caixa->conta_id ?? $this->resolverContaCaixa((int) $caixa->empresa_id)->id,
            'caixa_id' => $caixa->id,
            'tipo' => TipoLancamento::Debito,
            'categoria' => 'sangria',
            'valor' => $valor,
            'data' => now()->toDateString(),
            'descricao' => $descricao,
        ]);
    }

    public function registrarReforco(Caixa $caixa, float $valor, string $descricao): Lancamento
    {
        return Lancamento::create([
            'rede_id' => (int) $caixa->rede_id,
            'empresa_id' => (int) $caixa->empresa_id,
            'conta_id' => $caixa->conta_id ?? $this->resolverContaCaixa((int) $caixa->empresa_id)->id,
            'caixa_id' => $caixa->id,
            'tipo' => TipoLancamento::Credito,
            'categoria' => 'reforco',
            'valor' => $valor,
            'data' => now()->toDateString(),
            'descricao' => $descricao,
        ]);
    }

    /**
     * Baixa uma parcela de Pagamento (contas a receber).
     * Atualiza a parcela, cria a BaixaPagamento (o registro do recebimento por
     * forma) e, so quando a forma cai na gaveta (dinheiro), o Lancamento de
     * credito; por fim recalcula o status do titulo pai.
     *
     * $parcelasCartao (nº de parcelas no cartão do adquirente) e aceito por
     * compatibilidade, mas ignorado no regime "fluxo, nao saldo" (ADR-0011) —
     * nao ha mais recebivel/agenda do adquirente. Removido de vez na Fatia 2.
     */
    public function darBaixaParcelaPagamento(
        ParcelaPagamento $parcela,
        float $valor,
        FormaPagamento $forma,
        ?string $observacao = null,
        float $multa = 0,
        float $juros = 0,
        float $desconto = 0,
        ?int $parcelasCartao = null,
    ): BaixaPagamento {
        return $this->aplicarBaixaParcela(
            parcela: $parcela,
            valor: $valor,
            forma: $forma,
            observacao: $observacao,
            multa: $multa,
            juros: $juros,
            desconto: $desconto,
            baixaClass: BaixaPagamento::class,
            parcelaFk: 'parcela_pagamento_id',
            tipoLancamento: TipoLancamento::Credito,
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
     * Lancamento de debito na conta destino + atualiza status da parcela e do titulo.
     */
    public function darBaixaParcelaDespesa(
        ParcelaDespesa $parcela,
        float $valor,
        FormaPagamento $forma,
        ?string $observacao = null,
        float $multa = 0,
        float $juros = 0,
        float $desconto = 0,
    ): BaixaDespesa {
        return $this->aplicarBaixaParcela(
            parcela: $parcela,
            valor: $valor,
            forma: $forma,
            observacao: $observacao,
            multa: $multa,
            juros: $juros,
            desconto: $desconto,
            baixaClass: BaixaDespesa::class,
            parcelaFk: 'parcela_despesa_id',
            tipoLancamento: TipoLancamento::Debito,
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
     *
     * Regime "fluxo, nao saldo" (ADR-0011): a Baixa E o registro do recebimento/
     * pagamento por forma (o painel do dia le por ela). O eixo de decisao e a
     * conta destino da forma:
     *  - conta CAIXA (dinheiro fisico na gaveta): exige caixa aberto e grava UM
     *    Lancamento (credito no recebimento / debito na despesa) com o caixa_id
     *    da sessao — mantem o saldo reconciliavel da gaveta.
     *  - qualquer outra conta (cartao/pix/boleto/crediario/banco): so a Baixa
     *    registra o fluxo; nao mantemos saldo de banco (que desatualiza fora do
     *    sistema). Nada de Lancamento nem recebivel.
     *
     * Em ambos os casos a Baixa e criada, a parcela quitada e o titulo recalculado.
     *
     * @param  ParcelaPagamento|ParcelaDespesa  $parcela
     * @param  class-string<BaixaPagamento|BaixaDespesa>  $baixaClass
     * @param  \Closure():void  $recalcularTitulo
     * @return BaixaPagamento|BaixaDespesa
     */
    private function aplicarBaixaParcela(
        $parcela,
        float $valor,
        FormaPagamento $forma,
        ?string $observacao,
        float $multa,
        float $juros,
        float $desconto,
        string $baixaClass,
        string $parcelaFk,
        TipoLancamento $tipoLancamento,
        string $movimentoFk,
        string $tituloLabel,
        int $tituloId,
        string $mensagemSemCaixa,
        string $mensagemLiquidoInvalido,
        \Closure $recalcularTitulo,
    ) {
        return DB::transaction(function () use (
            $parcela, $valor, $forma, $observacao, $multa, $juros, $desconto,
            $baixaClass, $parcelaFk, $tipoLancamento, $movimentoFk,
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

            $empresaId = (int) $parcela->empresa_id;
            $conta = $this->resolverContaDestino($forma, $empresaId);
            $vaiPraGaveta = $conta->tipo === TipoConta::Caixa;

            // Exige caixa aberto so quando o dinheiro entra na gaveta na hora.
            $caixa = null;
            if ($vaiPraGaveta) {
                $caixa = $this->caixaAbertoDaEmpresa($empresaId, now()->toDateString());
                if (! $caixa) {
                    throw new NegocioException($mensagemSemCaixa);
                }
            }

            $baixa = $baixaClass::create([
                $parcelaFk => $parcela->id,
                'caixa_id' => $caixa?->id,
                'conta_id' => $conta->id,
                'valor' => $valor,
                'multa' => $multa,
                'juros' => $juros,
                'desconto' => $desconto,
                'forma_pagamento_id' => $forma->id,
                'forma_pagamento_nome' => $forma->nome,
                'data' => now(),
                'observacao' => $observacao,
            ]);

            $parcela->update([
                'valor_pago' => (float) $parcela->valor_pago + $valor,
                'forma_pagamento_id' => $forma->id,
                'forma_pagamento_nome' => $forma->nome,
            ]);

            $parcela->refresh();

            if ((float) $parcela->valor_pago + 0.001 >= (float) $parcela->valor) {
                $parcela->update(['status' => StatusParcela::Pago]);
            }

            // So a gaveta (dinheiro fisico) gera Lancamento — mantem o saldo
            // reconciliavel da sessao. Cartao/pix/boleto/crediario/banco: a Baixa
            // ja registrou o fluxo (recebido/pago por forma no dia); nao mantemos
            // saldo de banco (ADR-0011).
            if ($vaiPraGaveta) {
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

                Lancamento::create([
                    'rede_id' => (int) $parcela->rede_id,
                    'empresa_id' => $empresaId,
                    'conta_id' => (int) $conta->id,
                    'caixa_id' => $caixa->id,
                    'tipo' => $tipoLancamento,
                    'categoria' => 'movimento',
                    'valor' => $totalLiquido,
                    'data' => now()->toDateString(),
                    'descricao' => $descricao,
                    'forma_pagamento_nome' => $forma->nome,
                    $movimentoFk => $baixa->id,
                ]);
            }

            $recalcularTitulo();

            return $baixa;
        });
    }

    /**
     * Estorna um título de Pagamento (na prática: cancelamento da venda).
     * Regime "fluxo, nao saldo" (ADR-0011):
     * - Marca cada baixa como estornada (`estornado_em`) — marcador unico; o painel
     *   do dia por forma neta o recebido pela data do estorno.
     * - So a baixa da gaveta (dinheiro) tem Lancamento a reverter: gera um
     *   contra-lançamento (débito) na MESMA conta/caixa em que entrou, anulando o
     *   crédito. Cartao/pix/boleto/banco nao tem lançamento — nada a reverter.
     * - Parcelas pendentes: marcadas como canceladas. Título: status = estornado.
     *
     * Se o lançamento de origem entrou num caixa que agora está fechado, exige
     * reabri-lo antes (NegocioException) — não altera o saldo de um dia fechado.
     */
    public function estornarPagamento(Pagamento $pagamento): void
    {
        DB::transaction(function () use ($pagamento) {
            $pagamento->load('parcelas.baixas');

            foreach ($pagamento->parcelas as $parcela) {
                foreach ($parcela->baixas as $baixa) {
                    // So a gaveta tem lançamento de origem (dinheiro). Localiza para
                    // reusar conta/caixa e barrar estorno em caixa fechado.
                    $origem = Lancamento::withoutGlobalScope('empresa')
                        ->where('baixa_pagamento_id', $baixa->id)
                        ->where('categoria', 'movimento')
                        ->first();

                    if ($origem !== null && $origem->caixa_id !== null) {
                        $caixaOrigem = Caixa::withoutGlobalScope('empresa')->find($origem->caixa_id);
                        if ($caixaOrigem && $caixaOrigem->status === StatusCaixa::Fechado) {
                            throw new NegocioException(
                                'Não é possível estornar: o caixa em que o recebimento entrou está fechado. '
                                .'Reabra o caixa da respectiva data antes de cancelar a venda.'
                            );
                        }
                    }

                    // Marcador do fluxo: a baixa foi estornada (o painel do dia neta por aqui).
                    if ($baixa->estornado_em === null) {
                        $baixa->update(['estornado_em' => now()]);
                    }

                    // Contra-lançamento so quando houve entrada na gaveta.
                    if ($origem !== null) {
                        Lancamento::create([
                            'rede_id' => (int) $baixa->rede_id,
                            'empresa_id' => (int) $baixa->empresa_id,
                            'conta_id' => (int) $origem->conta_id,
                            'caixa_id' => $origem->caixa_id,
                            'tipo' => TipoLancamento::Debito,
                            'categoria' => 'estorno',
                            'valor' => $baixa->valorTotal(),
                            'data' => now()->toDateString(),
                            'descricao' => "Estorno da parcela {$parcela->numero}/{$parcela->total} do pagamento #{$pagamento->id}",
                            'forma_pagamento_nome' => $baixa->forma_pagamento_nome,
                            'baixa_pagamento_id' => $baixa->id,
                        ]);
                    }
                }

                if ($parcela->status === StatusParcela::Pendente) {
                    $parcela->update(['status' => StatusParcela::Cancelado]);
                }
            }

            $pagamento->update(['status' => StatusPagamento::Estornado]);
        });
    }
}
