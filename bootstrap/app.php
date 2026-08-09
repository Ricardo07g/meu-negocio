<?php

declare(strict_types=1);

use App\Exceptions\{ConflitoAgendamentoException, EmpresaNaoEncontradaException, NegocioException, PlanoLimiteException, TenantNaoEncontradoException};
use App\Http\Middleware\{AplicarContextoEmpresa, AutenticarCron, VerificarEmpresa, VerificarPlano, VerificarRede, VerificarTurnstile};
use App\Modules\Arquivo\Console\LimparRascunhosArquivo;
use App\Modules\Conta\Console\LimparExportacoes;
use App\Modules\Tenant\Console\ExpirarTrial;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Fora de qualquer grupo: o agendador HTTP nao usa sessao/CSRF/tenant.
        then: function (): void {
            Route::group([], __DIR__.'/../routes/cron.php');
        },
    )
    ->withCommands([
        LimparRascunhosArquivo::class,
        LimparExportacoes::class,
        ExpirarTrial::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (e afins) terminam o TLS no proxy e encaminham via HTTP.
        // Confiar no proxy faz o Laravel enxergar o esquema HTTPS correto (assets, cookies, redirects).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'cron.auth' => AutenticarCron::class,
            'turnstile' => VerificarTurnstile::class,
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
