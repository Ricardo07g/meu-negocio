<?php

namespace Tests\Feature\Despesa;

use App\Enums\FormaPagamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusDespesa;
use App\Enums\StatusParcela;
use App\Enums\TipoMovimentoCaixa;
use App\Exceptions\NegocioException;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Caixa\Services\CaixaService;
use App\Modules\Despesa\Models\Despesa;
use Carbon\Carbon;
use Database\Factories\CaixaFactory;
use Database\Factories\DespesaFactory;
use Database\Factories\ParcelaDespesaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Cobre a baixa (pagamento) de parcela de Despesa via CaixaService:
 *  - Baixa parcial: parcela continua Pendente com valor_pago acumulado, e
 *    o titulo agregado vira Parcial.
 *  - Baixa total: parcela vira Paga e o titulo Pago.
 *  - Toda baixa gera BaixaDespesa + MovimentoCaixa de SAIDA no caixa aberto.
 *  - Sem caixa aberto a operacao e bloqueada com NegocioException.
 */
class BaixaParcelaDespesaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria uma despesa a prazo com 2 parcelas (R$ 100 cada) pendentes e
     * um caixa aberto, no contexto autenticado.
     *
     * @return array{contexto: array, despesa: Despesa, parcelas: Collection}
     */
    private function cenarioDuasParcelas(bool $comCaixaAberto = true): array
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 200.00,
            'status' => StatusDespesa::Pendente,
        ]);

        $parcelas = collect([1, 2])->map(fn (int $n) => ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => $n,
            'total' => 2,
            'valor' => 100.00,
            'data_vencimento' => Carbon::now()->addMonths($n)->format('Y-m-d'),
        ]));

        if ($comCaixaAberto) {
            CaixaFactory::new()->aberto()->create([
                'rede_id' => $contexto['rede']->id,
                'empresa_id' => $contexto['empresa']->id,
                'usuario_id' => $contexto['usuario']->id,
                'data' => today()->format('Y-m-d'),
            ]);
        }

        return compact('contexto', 'despesa', 'parcelas');
    }

    public function test_baixa_parcial_mantem_parcela_pendente_e_titulo_parcial(): void
    {
        $cenario = $this->cenarioDuasParcelas();
        $primeira = $cenario['parcelas']->first();

        $baixa = app(CaixaService::class)->darBaixaParcelaDespesa(
            parcela: $primeira,
            valor: 40.00,
            formaPagamento: FormaPagamento::Dinheiro,
        );

        $primeira->refresh();

        $this->assertInstanceOf(BaixaDespesa::class, $baixa);
        $this->assertSame(40.00, (float) $primeira->valor_pago);
        $this->assertSame(60.00, $primeira->saldoRestante(), 'Saldo restante deveria refletir a baixa parcial.');
        $this->assertSame(StatusParcela::Pendente, $primeira->status, 'Parcela com saldo aberto continua Pendente.');

        $despesa = $cenario['despesa']->fresh();
        $this->assertSame(StatusDespesa::Pendente, $despesa->status, 'Sem nenhuma parcela quitada, o titulo segue Pendente.');

        // Movimento de SAIDA no caixa, vinculado a baixa.
        $movimento = MovimentoCaixa::where('baixa_despesa_id', $baixa->id)->firstOrFail();
        $this->assertSame(TipoMovimentoCaixa::Saida, $movimento->tipo, 'Despesa gera saida no caixa.');
        $this->assertSame(40.00, (float) $movimento->valor);
    }

    public function test_baixa_total_quita_parcela_e_titulo(): void
    {
        $cenario = $this->cenarioDuasParcelas();
        [$primeira, $segunda] = [$cenario['parcelas']->first(), $cenario['parcelas']->last()];

        $service = app(CaixaService::class);

        $service->darBaixaParcelaDespesa($primeira, 100.00, FormaPagamento::Pix);
        $primeira->refresh();
        $this->assertSame(StatusParcela::Pago, $primeira->status, 'Parcela quitada deveria ficar Paga.');
        $this->assertSame(StatusDespesa::Parcial, $cenario['despesa']->fresh()->status, 'Com 1 de 2 paga, o titulo deveria ficar Parcial.');

        $service->darBaixaParcelaDespesa($segunda, 100.00, FormaPagamento::Pix);
        $segunda->refresh();
        $this->assertSame(StatusParcela::Pago, $segunda->status);
        $this->assertSame(StatusDespesa::Paga, $cenario['despesa']->fresh()->status, 'Com todas pagas, o titulo deveria ficar Pago.');

        // Duas saidas no caixa, somando o valor total.
        $saidas = MovimentoCaixa::where('tipo', TipoMovimentoCaixa::Saida)->sum('valor');
        $this->assertSame(200.00, (float) $saidas, 'O caixa deveria registrar saida igual ao valor pago.');
    }

    public function test_baixa_com_multa_e_juros_aumenta_saida_no_caixa(): void
    {
        $cenario = $this->cenarioDuasParcelas();
        $primeira = $cenario['parcelas']->first();

        $baixa = app(CaixaService::class)->darBaixaParcelaDespesa(
            parcela: $primeira,
            valor: 100.00,
            formaPagamento: FormaPagamento::Boleto,
            observacao: null,
            multa: 5.00,
            juros: 3.00,
            desconto: 0,
        );

        $primeira->refresh();
        $this->assertSame(StatusParcela::Pago, $primeira->status, 'Principal cobre o valor da parcela: fica Paga.');

        // O movimento de caixa reflete o liquido (principal + multa + juros).
        $movimento = MovimentoCaixa::where('baixa_despesa_id', $baixa->id)->firstOrFail();
        $this->assertSame(108.00, (float) $movimento->valor, 'A saida liquida deve incluir multa e juros.');
        $this->assertSame(108.00, $primeira->valorPagoLiquido(), 'valorPagoLiquido soma principal + multa + juros - desconto.');
    }

    public function test_nao_baixa_parcela_sem_caixa_aberto(): void
    {
        $cenario = $this->cenarioDuasParcelas(comCaixaAberto: false);
        $primeira = $cenario['parcelas']->first();

        $this->expectException(NegocioException::class);
        $this->expectExceptionMessage('É necessário um caixa aberto para registrar o pagamento.');

        try {
            app(CaixaService::class)->darBaixaParcelaDespesa(
                parcela: $primeira,
                valor: 100.00,
                formaPagamento: FormaPagamento::Dinheiro,
            );
        } finally {
            // Nada deveria ter sido persistido (transacao revertida).
            $this->assertSame(0, BaixaDespesa::count(), 'Sem caixa aberto, nenhuma baixa deveria ser criada.');
            $this->assertSame(0.0, (float) $primeira->fresh()->valor_pago, 'A parcela nao deveria ter sido alterada.');
        }
    }

    public function test_caixa_fechado_nao_conta_como_aberto(): void
    {
        $contexto = $this->criarRedeAutenticada();

        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'valor_total' => 100.00,
        ]);

        $parcela = ParcelaDespesaFactory::new()->pendente()->create([
            'despesa_id' => $despesa->id,
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'numero' => 1,
            'total' => 1,
            'valor' => 100.00,
        ]);

        // So existe caixa FECHADO no dia: deve ser tratado como "sem caixa".
        CaixaFactory::new()->fechado()->create([
            'rede_id' => $contexto['rede']->id,
            'empresa_id' => $contexto['empresa']->id,
            'usuario_id' => $contexto['usuario']->id,
            'data' => today()->format('Y-m-d'),
            'status' => StatusCaixa::Fechado,
        ]);

        $this->expectException(NegocioException::class);

        app(CaixaService::class)->darBaixaParcelaDespesa(
            parcela: $parcela,
            valor: 100.00,
            formaPagamento: FormaPagamento::Dinheiro,
        );
    }
}
