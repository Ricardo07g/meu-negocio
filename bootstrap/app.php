<?php

use App\Exceptions\ConflitoAgendamentoException;
use App\Exceptions\EmpresaNaoEncontradaException;
use App\Exceptions\NegocioException;
use App\Exceptions\PlanoLimiteException;
use App\Exceptions\TenantNaoEncontradoException;
use App\Http\Middleware\VerificarEmpresa;
use App\Http\Middleware\VerificarPlano;
use App\Http\Middleware\VerificarRede;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verificar.rede' => VerificarRede::class,
            'verificar.empresa' => VerificarEmpresa::class,
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
