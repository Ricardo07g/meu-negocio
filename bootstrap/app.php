<?php

declare(strict_types=1);

use App\Exceptions\{ConflitoAgendamentoException, EmpresaNaoEncontradaException, NegocioException, PlanoLimiteException, TenantNaoEncontradoException};
use App\Http\Middleware\{AplicarContextoEmpresa, VerificarEmpresa, VerificarPlano, VerificarRede};
use App\Modules\Arquivo\Console\LimparRascunhosArquivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        LimparRascunhosArquivo::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verificar.rede' => VerificarRede::class,
            'verificar.empresa' => VerificarEmpresa::class,
            'aplicar.contexto.empresa' => AplicarContextoEmpresa::class,
            'verificar.plano' => VerificarPlano::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (PlanoLimiteException $e) {
            return redirect()->route('dashboard')
                ->with('erro', $e->getMessage());
        });

        $exceptions->renderable(function (TenantNaoEncontradoException $e) {
            return redirect()->route('login')
                ->withErrors(['rede' => $e->getMessage()]);
        });

        $exceptions->renderable(function (EmpresaNaoEncontradaException $e) {
            return redirect()->route('dashboard')
                ->with('erro', $e->getMessage());
        });

        $exceptions->renderable(function (ConflitoAgendamentoException $e) {
            return back()
                ->with('erro', $e->getMessage())
                ->withInput();
        });

        $exceptions->renderable(function (NegocioException $e) {
            return back()
                ->with('erro', $e->getMessage())
                ->withInput();
        });
    })->create();
