<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verificar.rede' => \App\Http\Middleware\VerificarRede::class,
            'verificar.empresa' => \App\Http\Middleware\VerificarEmpresa::class,
            'verificar.plano' => \App\Http\Middleware\VerificarPlano::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\App\Exceptions\PlanoLimiteException $e) {
            return redirect()->route('dashboard')
                ->with('erro', $e->getMessage());
        });

        $exceptions->renderable(function (\App\Exceptions\TenantNaoEncontradoException $e) {
            return redirect()->route('login')
                ->withErrors(['rede' => $e->getMessage()]);
        });

        $exceptions->renderable(function (\App\Exceptions\EmpresaNaoEncontradaException $e) {
            return redirect()->route('dashboard')
                ->with('erro', $e->getMessage());
        });

        $exceptions->renderable(function (\App\Exceptions\ConflitoAgendamentoException $e) {
            return back()
                ->with('erro', $e->getMessage())
                ->withInput();
        });

        $exceptions->renderable(function (\App\Exceptions\NegocioException $e) {
            return back()
                ->with('erro', $e->getMessage())
                ->withInput();
        });
    })->create();
