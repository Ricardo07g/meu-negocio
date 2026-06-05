<?php

namespace Tests\Feature;

use Database\Factories\AgendamentoFactory;
use Database\Factories\CaixaFactory;
use Database\Factories\CategoriaDespesaFactory;
use Database\Factories\CategoriaProdutoFactory;
use Database\Factories\ClienteFactory;
use Database\Factories\DespesaFactory;
use Database\Factories\MovimentoCaixaFactory;
use Database\Factories\MovimentoEstoqueFactory;
use Database\Factories\PagamentoFactory;
use Database\Factories\ParcelaDespesaFactory;
use Database\Factories\ParcelaPagamentoFactory;
use Database\Factories\ProdutoFactory;
use Database\Factories\ServicoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test de sanidade das factories.
 *
 * Garante que toda factory em database/factories/ persiste no banco
 * respeitando o contexto multi-tenant (rede_id/empresa_id) e as
 * dependencias entre modelos. Roda dentro de criarRedeAutenticada()
 * para exercitar o caminho real (usuario autenticado + sessao de empresa).
 *
 * Os models do projeto nao usam o trait HasFactory (decisao de produto),
 * entao as factories sao acionadas pela classe Factory diretamente
 * (`XxxFactory::new()`), nao por `Model::factory()`.
 */
class _FactoriesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_de_catalogo_persistem(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servicoAvulso = ServicoFactory::new()->avulso()->create(['rede_id' => $rede->id]);
        $servicoEtapas = ServicoFactory::new()->etapas(8)->create(['rede_id' => $rede->id]);
        $categoriaProduto = CategoriaProdutoFactory::new()->create(['rede_id' => $rede->id]);
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $rede->id,
            'categoria_produto_id' => $categoriaProduto->id,
        ]);
        $categoriaDespesa = CategoriaDespesaFactory::new()->create(['rede_id' => $rede->id]);

        $this->assertModelExists($cliente);
        $this->assertModelExists($servicoAvulso);
        $this->assertModelExists($servicoEtapas);
        $this->assertModelExists($categoriaProduto);
        $this->assertModelExists($produto);
        $this->assertModelExists($categoriaDespesa);

        $this->assertTrue($servicoAvulso->isUnico());
        $this->assertTrue($servicoEtapas->isEtapas());
        $this->assertSame(8, $servicoEtapas->qtd_etapas);

        // Tudo na rede do contexto autenticado.
        $this->assertSame($rede->id, $cliente->rede_id);
        $this->assertSame($rede->id, $produto->rede_id);
    }

    public function test_factories_financeiras_persistem(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $pagamento = PagamentoFactory::new()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
        ]);
        $parcelaPagamento = ParcelaPagamentoFactory::new()->paga()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'pagamento_id' => $pagamento->id,
        ]);

        $categoriaDespesa = CategoriaDespesaFactory::new()->create(['rede_id' => $rede->id]);
        $despesa = DespesaFactory::new()->aPrazo()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'categoria_despesa_id' => $categoriaDespesa->id,
        ]);
        $parcelaDespesa = ParcelaDespesaFactory::new()->pendente()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'despesa_id' => $despesa->id,
        ]);

        $this->assertModelExists($pagamento);
        $this->assertModelExists($parcelaPagamento);
        $this->assertModelExists($despesa);
        $this->assertModelExists($parcelaDespesa);

        $this->assertSame($pagamento->id, $parcelaPagamento->pagamento_id);
        $this->assertSame($despesa->id, $parcelaDespesa->despesa_id);
        $this->assertSame($empresa->id, $parcelaPagamento->empresa_id);
        $this->assertSame($empresa->id, $parcelaDespesa->empresa_id);
    }

    public function test_factories_de_caixa_e_estoque_persistem(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $caixa = CaixaFactory::new()->aberto()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'usuario_id' => $contexto['usuario']->id,
        ]);
        $movimentoCaixa = MovimentoCaixaFactory::new()->entrada()->create([
            'caixa_id' => $caixa->id,
        ]);

        $produto = ProdutoFactory::new()->create(['rede_id' => $rede->id]);
        $movimentoEstoque = MovimentoEstoqueFactory::new()->entrada()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
        ]);

        $this->assertModelExists($caixa);
        $this->assertModelExists($movimentoCaixa);
        $this->assertModelExists($movimentoEstoque);

        $this->assertSame($caixa->id, $movimentoCaixa->caixa_id);
        $this->assertSame($produto->id, $movimentoEstoque->produto_id);
    }

    public function test_factory_de_agendamento_persiste(): void
    {
        $contexto = $this->criarRedeAutenticada();
        $rede = $contexto['rede'];
        $empresa = $contexto['empresa'];

        $cliente = ClienteFactory::new()->create(['rede_id' => $rede->id]);
        $servico = ServicoFactory::new()->avulso()->create(['rede_id' => $rede->id]);

        $agendamento = AgendamentoFactory::new()->confirmado()->create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'servico_id' => $servico->id,
            'atendente_id' => $contexto['usuario']->id,
        ]);

        $this->assertModelExists($agendamento);
        $this->assertSame($cliente->id, $agendamento->cliente_id);
        $this->assertSame($servico->id, $agendamento->servico_id);
        $this->assertSame($empresa->id, $agendamento->empresa_id);
    }
}
