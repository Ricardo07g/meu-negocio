<?php

declare(strict_types=1);

namespace Tests\Feature\Venda;

use App\Enums\StatusVendaProduto;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Venda\Models\VendaProduto;
use Database\Factories\{ClienteFactory, PagamentoFactory, ParcelaPagamentoFactory, ProdutoFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaTenant;
use Tests\TestCase;

/**
 * Valida as duas acoes destrutivas da Venda:
 *  - Cancelar: desfaz os lancamentos (estoque/pagamento), mantem o registro.
 *  - Excluir : desfaz os lancamentos (mesmo estorno) E aplica soft delete.
 *
 * Foca na venda de produto porque ela toca estoque e pagamento — o caso mais
 * completo. Usa venda a prazo com parcela pendente (valorPago = 0) para que o
 * estorno nao exija caixa aberto.
 */
class VendaExcluirTest extends TestCase
{
    use CriaTenant;
    use RefreshDatabase;

    private function montarContexto(): array
    {
        $contexto = $this->criarRedeAutenticada();

        return [
            'rede' => $contexto['rede'],
            'empresa' => $contexto['empresa'],
            'usuario' => $contexto['usuario'],
            'cliente' => ClienteFactory::new()->create(['rede_id' => $contexto['rede']->id]),
        ];
    }

    /**
     * @return array{venda: VendaProduto, produto: Produto, pagamento: Pagamento}
     */
    private function criarVendaProduto(array $ctx, int $produtoQtd = 8, StatusVendaProduto $status = StatusVendaProduto::Ativa): array
    {
        $produto = ProdutoFactory::new()->create([
            'rede_id' => $ctx['rede']->id,
            'valor_venda' => 50.00,
            'quantidade' => $produtoQtd,
        ]);

        $venda = VendaProduto::create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'usuario_id' => $ctx['usuario']->id,
            'data' => now()->format('Y-m-d'),
            'subtotal' => 100.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => 100.00,
            'status' => $status,
        ]);

        $venda->itens()->create([
            'produto_id' => $produto->id,
            'descricao' => $produto->nome,
            'quantidade' => 2,
            'valor_unitario' => 50.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'subtotal' => 100.00,
        ]);

        $pagamento = PagamentoFactory::new()->aPrazo()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'venda_produto_id' => $venda->id,
            'valor_total' => 100.00,
        ]);
        ParcelaPagamentoFactory::new()->pendente()->create([
            'rede_id' => $ctx['rede']->id,
            'empresa_id' => $ctx['empresa']->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 100.00,
        ]);

        return ['venda' => $venda->fresh(), 'produto' => $produto, 'pagamento' => $pagamento];
    }

    public function test_excluir_venda_ativa_devolve_estoque_e_aplica_soft_delete(): void
    {
        $ctx = $this->montarContexto();
        ['venda' => $venda, 'produto' => $produto, 'pagamento' => $pagamento] = $this->criarVendaProduto($ctx);

        $resp = $this->delete(route('vendas.excluir-produto', $venda));

        $resp->assertRedirect(route('vendas.index'));
        $resp->assertSessionHas('sucesso');

        // Estoque devolvido (8 + 2) + movimento de entrada registrado.
        $this->assertSame(10, $produto->fresh()->quantidade);
        $this->assertDatabaseHas('movimentos_estoque', [
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 2,
        ]);

        // Soft delete na venda e no pagamento (some da listagem / contas a receber).
        $this->assertSoftDeleted($venda);
        $this->assertSoftDeleted('pagamentos', ['id' => $pagamento->id]);
    }

    public function test_excluir_venda_ja_cancelada_nao_estorna_de_novo(): void
    {
        $ctx = $this->montarContexto();
        ['venda' => $venda, 'produto' => $produto] = $this->criarVendaProduto($ctx, produtoQtd: 10, status: StatusVendaProduto::Cancelada);

        $resp = $this->delete(route('vendas.excluir-produto', $venda));

        $resp->assertRedirect(route('vendas.index'));

        // Como ja estava cancelada, o estoque NAO e devolvido de novo.
        $this->assertSame(10, $produto->fresh()->quantidade);
        $this->assertDatabaseCount('movimentos_estoque', 0);

        // Ainda assim aplica o soft delete.
        $this->assertSoftDeleted($venda);
    }

    public function test_excluir_sem_permissao_retorna_403(): void
    {
        $ctx = $this->montarContexto();
        ['venda' => $venda] = $this->criarVendaProduto($ctx);

        // Papel sem nenhuma permissao (sem agendamento.excluir).
        $semPermissao = $this->criarUsuarioComum($ctx['rede'], $ctx['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$ctx['empresa']->id]]);

        $resp = $this->delete(route('vendas.excluir-produto', $venda));

        $resp->assertForbidden();
        $this->assertNotSoftDeleted($venda);
    }

    public function test_cancelar_produto_sem_permissao_retorna_403(): void
    {
        $ctx = $this->montarContexto();
        ['venda' => $venda] = $this->criarVendaProduto($ctx);

        $semPermissao = $this->criarUsuarioComum($ctx['rede'], $ctx['empresa'], 'Recepcao');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semPermissao);
        session(['empresas_atuais' => [$ctx['empresa']->id]]);

        $resp = $this->patch(route('vendas.cancelar-produto', $venda));

        $resp->assertForbidden();
        $this->assertSame(StatusVendaProduto::Ativa, $venda->fresh()->status);
    }
}
