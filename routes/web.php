<?php

declare(strict_types=1);

use App\Modules\Agenda\Controllers\AgendaController;
use App\Modules\Auth\Controllers\{EsqueciSenhaController, LoginController, RedefinirSenhaController, RegistrarController};
use App\Modules\Caixa\Controllers\CaixaController;
use App\Modules\Cliente\Controllers\ClienteController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Despesa\Controllers\{CategoriaDespesaController, DespesaController};
use App\Modules\Estoque\Controllers\MovimentoEstoqueController;
use App\Modules\Pagamento\Controllers\PagamentoController;
use App\Modules\PerfilAcesso\Controllers\PerfilAcessoController;
use App\Modules\Produto\Controllers\{CategoriaProdutoController, ProdutoController};
use App\Modules\Servico\Controllers\ServicoController;
use App\Modules\Tenant\Controllers\{AssinaturaController, EmpresaController};
use App\Modules\Usuario\Controllers\{PerfilController, UsuarioController};
use App\Modules\Venda\Controllers\VendaController;
use Illuminate\Support\Facades\Route;

// Pagina inicial: landing publica (usuario logado vai direto para o dashboard)
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('home');

// Autenticacao (guest)
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('registrar', [RegistrarController::class, 'showRegistrationForm'])->name('registrar');
    Route::post('registrar', [RegistrarController::class, 'register'])->middleware('throttle:5,1');

    // Recuperacao de senha
    Route::get('esqueci-senha', [EsqueciSenhaController::class, 'showLinkRequestForm'])->name('senha.solicitar');
    Route::post('esqueci-senha', [EsqueciSenhaController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:5,1')
        ->name('senha.solicitar.enviar');
    Route::get('redefinir-senha/{token}', [RedefinirSenhaController::class, 'showResetForm'])->name('senha.redefinir.form');
    Route::post('redefinir-senha', [RedefinirSenhaController::class, 'reset'])->name('senha.redefinir');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Rotas autenticadas com verificacao de rede
Route::middleware(['auth', 'verificar.rede'])->group(function () {

    // Meu Perfil — self-service (fora do verificar.empresa, perfil nao depende de empresa selecionada)
    Route::get('perfil', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('perfil', [PerfilController::class, 'atualizar'])->name('perfil.atualizar');
    Route::post('perfil/senha', [PerfilController::class, 'atualizarSenha'])->name('perfil.senha');

    // Rotas que necessitam empresa
    Route::middleware(['verificar.empresa', 'aplicar.contexto.empresa'])->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Agenda
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::get('agenda/json', [AgendaController::class, 'json'])->name('agenda.json');
        Route::post('agenda/criar-rapido', [AgendaController::class, 'criarRapido'])->name('agenda.criar-rapido');
        Route::get('agenda/{agendamento}', [AgendaController::class, 'show'])->name('agenda.show');
        Route::get('agenda/{agendamento}/editar', [AgendaController::class, 'edit'])->name('agenda.edit');
        Route::put('agenda/{agendamento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::patch('agenda/{agendamento}/reagendar', [AgendaController::class, 'reagendar'])->name('agenda.reagendar');
        Route::patch('agenda/{agendamento}/confirmar', [AgendaController::class, 'confirmar'])->name('agenda.confirmar');
        Route::patch('agenda/{agendamento}/finalizar', [AgendaController::class, 'finalizar'])->name('agenda.finalizar');
        Route::patch('agenda/{agendamento}/cancelar', [AgendaController::class, 'cancelar'])->name('agenda.cancelar');

        // Vendas
        Route::get('vendas', [VendaController::class, 'index'])->name('vendas.index');
        Route::get('vendas/nova', [VendaController::class, 'create'])->name('vendas.create');
        Route::post('vendas', [VendaController::class, 'store'])->name('vendas.store');
        Route::patch('vendas/unico/{agendamento}/cancelar', [VendaController::class, 'cancelarUnico'])->name('vendas.cancelar-unico');
        Route::patch('vendas/etapas/{etapas}/cancelar', [VendaController::class, 'cancelarEtapas'])->name('vendas.cancelar-etapas');
        Route::patch('vendas/produto/{vendaProduto}/cancelar', [VendaController::class, 'cancelarProduto'])->name('vendas.cancelar-produto');
        Route::get('vendas/recibo/{tipo}/{id}', [VendaController::class, 'recibo'])->name('vendas.recibo')->whereIn('tipo', ['unico', 'etapas', 'produto']);
        Route::get('vendas/unico/{agendamento}/editar', [VendaController::class, 'editUnico'])->name('vendas.edit-unico');
        Route::patch('vendas/unico/{agendamento}', [VendaController::class, 'updateUnico'])->name('vendas.update-unico');
        Route::get('vendas/etapas/{etapas}/editar', [VendaController::class, 'editEtapas'])->name('vendas.edit-etapas');
        Route::patch('vendas/etapas/{etapas}', [VendaController::class, 'updateEtapas'])->name('vendas.update-etapas');
        Route::get('vendas/produto/{vendaProduto}/editar', [VendaController::class, 'editProduto'])->name('vendas.edit-produto');
        Route::patch('vendas/produto/{vendaProduto}', [VendaController::class, 'updateProduto'])->name('vendas.update-produto');

        // Clientes
        Route::get('clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::resource('clientes', ClienteController::class);

        // Servicos
        Route::get('servicos/buscar', [ServicoController::class, 'buscar'])->name('servicos.buscar');
        Route::resource('servicos', ServicoController::class);

        // Financeiro (verificar plano)
        Route::middleware(['verificar.plano:financeiro'])->group(function () {
            // Contas a Receber
            Route::get('pagamentos', [PagamentoController::class, 'index'])->name('pagamentos.index');
            Route::get('pagamentos/{pagamento}/recibo', [PagamentoController::class, 'recibo'])->name('pagamentos.recibo');
            Route::get('contas-a-receber', [PagamentoController::class, 'contasAReceber'])->name('pagamentos.contas-a-receber');

            // Parcelas de contas a receber
            Route::get('parcelas-pagamento/{parcela}/baixa', [PagamentoController::class, 'baixaParcelaForm'])->name('parcelas-pagamento.baixa-form');
            Route::post('parcelas-pagamento/{parcela}/baixa', [PagamentoController::class, 'baixaParcela'])->name('parcelas-pagamento.baixa');
            Route::patch('parcelas-pagamento/{parcela}/renegociar', [PagamentoController::class, 'renegociarParcela'])->name('parcelas-pagamento.renegociar');
            Route::patch('parcelas-pagamento/{parcela}/cancelar', [PagamentoController::class, 'cancelarParcela'])->name('parcelas-pagamento.cancelar');

            // Contas a Pagar
            Route::get('contas-a-pagar', [DespesaController::class, 'contasAPagar'])->name('despesas.contas-a-pagar');
            Route::get('despesas/{despesa}/recibo', [DespesaController::class, 'recibo'])->name('despesas.recibo');
            Route::patch('despesas/{despesa}/cancelar', [DespesaController::class, 'cancelar'])->name('despesas.cancelar');
            Route::resource('despesas', DespesaController::class)->except(['show', 'edit', 'update']);
            Route::resource('categorias-despesa', CategoriaDespesaController::class)->except(['show']);

            // Parcelas de contas a pagar
            Route::get('parcelas-despesa/{parcela}/baixa', [DespesaController::class, 'baixaParcelaForm'])->name('parcelas-despesa.baixa-form');
            Route::post('parcelas-despesa/{parcela}/baixa', [DespesaController::class, 'baixaParcela'])->name('parcelas-despesa.baixa');
            Route::patch('parcelas-despesa/{parcela}/cancelar', [DespesaController::class, 'cancelarParcela'])->name('parcelas-despesa.cancelar');

            // Caixa
            Route::get('caixas', [CaixaController::class, 'index'])->name('caixas.index');
            Route::post('caixas', [CaixaController::class, 'store'])->name('caixas.store');
            Route::get('caixas/{caixa}', [CaixaController::class, 'show'])->name('caixas.show');
            Route::patch('caixas/{caixa}/fechar', [CaixaController::class, 'fechar'])->name('caixas.fechar');
            Route::patch('caixas/{caixa}/reabrir', [CaixaController::class, 'reabrir'])->name('caixas.reabrir');
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
        Route::resource('empresas', EmpresaController::class)->except('show');
        Route::resource('usuarios', UsuarioController::class);
        Route::resource('perfis-acesso', PerfilAcessoController::class)->parameters(['perfis-acesso' => 'perfil_acesso']);
        Route::get('minha-assinatura', [AssinaturaController::class, 'index'])->name('assinatura.index');
        Route::post('minha-assinatura/transicionar', [AssinaturaController::class, 'transicionar'])->name('assinatura.transicionar');
    });
});
