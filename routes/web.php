<?php

use App\Modules\Agenda\Controllers\AgendaController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\RegistrarController;
use App\Modules\Cliente\Controllers\ClienteController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Despesa\Controllers\DespesaController;
use App\Modules\Estoque\Controllers\MovimentoEstoqueController;
use App\Modules\Pagamento\Controllers\PagamentoController;
use App\Modules\Papel\Controllers\PapelController;
use App\Modules\Produto\Controllers\CategoriaProdutoController;
use App\Modules\Produto\Controllers\ProdutoController;
use App\Modules\Servico\Controllers\ServicoController;
use App\Modules\Tenant\Controllers\EmpresaController;
use App\Modules\Usuario\Controllers\UsuarioController;
use App\Modules\Caixa\Controllers\CaixaController;
use App\Modules\Venda\Controllers\VendaController;
use Illuminate\Support\Facades\Route;

// Pagina inicial
Route::get('/', fn () => redirect()->route('login'));


// Autenticacao (guest)
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('registrar', [RegistrarController::class, 'showRegistrationForm'])->name('registrar');
    Route::post('registrar', [RegistrarController::class, 'register']);
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Rotas autenticadas com verificacao de rede
Route::middleware(['auth', 'verificar.rede'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rotas que necessitam empresa
    Route::middleware(['verificar.empresa'])->group(function () {

        // Agenda
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::get('agenda/json', [AgendaController::class, 'json'])->name('agenda.json');
        Route::get('agenda/{agendamento}', [AgendaController::class, 'show'])->name('agenda.show');
        Route::get('agenda/{agendamento}/editar', [AgendaController::class, 'edit'])->name('agenda.edit');
        Route::put('agenda/{agendamento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::patch('agenda/{agendamento}/confirmar', [AgendaController::class, 'confirmar'])->name('agenda.confirmar');
        Route::patch('agenda/{agendamento}/finalizar', [AgendaController::class, 'finalizar'])->name('agenda.finalizar');
        Route::patch('agenda/{agendamento}/cancelar', [AgendaController::class, 'cancelar'])->name('agenda.cancelar');

        // Vendas
        Route::get('vendas', [VendaController::class, 'index'])->name('vendas.index');
        Route::get('vendas/nova', [VendaController::class, 'create'])->name('vendas.create');
        Route::post('vendas', [VendaController::class, 'store'])->name('vendas.store');
        Route::get('vendas/avulso/{agendamento}', [VendaController::class, 'showAvulso'])->name('vendas.show-avulso');
        Route::get('vendas/pacote/{pacote}', [VendaController::class, 'showPacote'])->name('vendas.show-pacote');
        Route::patch('vendas/avulso/{agendamento}/cancelar', [VendaController::class, 'cancelarAvulso'])->name('vendas.cancelar-avulso');
        Route::patch('vendas/pacote/{pacote}/cancelar', [VendaController::class, 'cancelarPacote'])->name('vendas.cancelar-pacote');
        Route::get('vendas/produto/{vendaProduto}', [VendaController::class, 'showProduto'])->name('vendas.show-produto');
        Route::patch('vendas/produto/{vendaProduto}/cancelar', [VendaController::class, 'cancelarProduto'])->name('vendas.cancelar-produto');

        // Clientes
        Route::get('clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::resource('clientes', ClienteController::class);

        // Servicos
        Route::get('servicos/buscar', [ServicoController::class, 'buscar'])->name('servicos.buscar');
        Route::resource('servicos', ServicoController::class);

        // Financeiro (verificar plano)
        Route::middleware(['verificar.plano:financeiro'])->group(function () {
            Route::resource('pagamentos', PagamentoController::class)->only(['index', 'create', 'store', 'show']);
            Route::post('pagamentos/{pagamento}/baixa', [PagamentoController::class, 'baixa'])->name('pagamentos.baixa');
            Route::get('contas-a-receber', [PagamentoController::class, 'contasAReceber'])->name('pagamentos.contas-a-receber');
            Route::resource('despesas', DespesaController::class);

            // Caixa
            Route::get('caixas', [CaixaController::class, 'index'])->name('caixas.index');
            Route::post('caixas', [CaixaController::class, 'store'])->name('caixas.store');
            Route::get('caixas/{caixa}', [CaixaController::class, 'show'])->name('caixas.show');
            Route::patch('caixas/{caixa}/fechar', [CaixaController::class, 'fechar'])->name('caixas.fechar');
            Route::post('caixas/{caixa}/sangria', [CaixaController::class, 'sangria'])->name('caixas.sangria');
            Route::post('caixas/{caixa}/reforco', [CaixaController::class, 'reforco'])->name('caixas.reforco');
        });

        // Produtos (cadastro independente)
        Route::get('produtos/buscar', [ProdutoController::class, 'buscar'])->name('produtos.buscar');
        Route::resource('produtos', ProdutoController::class);
        Route::resource('categorias-produto', CategoriaProdutoController::class)->except(['show']);

        // Estoque - movimentação (verificar plano)
        Route::middleware(['verificar.plano:estoque'])->group(function () {
            Route::resource('movimentos-estoque', MovimentoEstoqueController::class)->only(['index', 'create', 'store']);
        });

        // Administracao
        Route::resource('empresas', EmpresaController::class);
        Route::resource('usuarios', UsuarioController::class);
        Route::resource('papeis', PapelController::class)->except(['show'])->parameters(['papeis' => 'papel']);
    });
});
