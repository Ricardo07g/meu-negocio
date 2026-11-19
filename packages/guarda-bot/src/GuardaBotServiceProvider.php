<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Ricardo07g\GuardaBot\Console\{DiagnosticarGuardaBot, InstalarGuardaBot};
use Ricardo07g\GuardaBot\Http\Middleware\{ProtegerContraBots, VerificarTurnstile};

/**
 * Registra o GuardaBot na aplicacao (auto-discovery via composer.json).
 *
 * - Config `guarda-bot` (publicavel com a tag `guarda-bot-config`).
 * - Views no namespace `guarda-bot`: <x-guarda-bot::protecao />,
 *   <x-guarda-bot::armadilha /> e <x-guarda-bot::turnstile />.
 * - Middlewares `guarda-bot` (pontuacao de risco) e `guarda-bot.turnstile`
 *   (desafio). Prefixados para nao colidir com aliases da aplicacao.
 * - Comandos `guarda-bot:instalar` e `guarda-bot:diagnosticar`.
 */
class GuardaBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/guarda-bot.php', 'guarda-bot');

        $this->app->singleton(AvaliadorDeRisco::class);
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'guarda-bot');

        $router->aliasMiddleware('guarda-bot', ProtegerContraBots::class);
        $router->aliasMiddleware('guarda-bot.turnstile', VerificarTurnstile::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/guarda-bot.php' => config_path('guarda-bot.php'),
            ], 'guarda-bot-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/guarda-bot'),
            ], 'guarda-bot-views');

            $this->commands([
                InstalarGuardaBot::class,
                DiagnosticarGuardaBot::class,
            ]);
        }
    }
}
