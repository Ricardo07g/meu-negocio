<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrarController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DespesaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\MovimentoEstoqueController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\PapelController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VendaController;
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

        // Clientes
        Route::resource('clientes', ClienteController::class);

        // Servicos
        Route::resource('servicos', ServicoController::class);

        // Financeiro (verificar plano)
        Route::middleware(['verificar.plano:financeiro'])->group(function () {
            Route::resource('pagamentos', PagamentoController::class)->only(['index', 'create', 'store', 'show']);
            Route::resource('despesas', DespesaController::class);
        });

        // Estoque (verificar plano)
        Route::middleware(['verificar.plano:estoque'])->group(function () {
            Route::resource('produtos', ProdutoController::class);
            Route::resource('movimentos-estoque', MovimentoEstoqueController::class)->only(['index', 'create', 'store']);
        });

        // Administracao
        Route::resource('empresas', EmpresaController::class);
        Route::resource('usuarios', UsuarioController::class);
        Route::resource('papeis', PapelController::class)->except(['show'])->parameters(['papeis' => 'papel']);
    });
});
