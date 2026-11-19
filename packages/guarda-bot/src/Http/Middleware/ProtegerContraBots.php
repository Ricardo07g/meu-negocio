<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ricardo07g\GuardaBot\{AvaliadorDeRisco, Modo, Veredito};
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `guarda-bot`: avalia a requisicao e age conforme o modo.
 *
 * - desligado: passa direto.
 * - observar:  registra o veredito com sinal e deixa passar.
 * - ativo:     registra e bloqueia a partir do limiar.
 *
 * O bloqueio responde como erro de formulario, com mensagem generica: nao
 * revela qual sinal pegou (isso so ensinaria o robo) e nunca devolve senha
 * para a tela.
 */
class ProtegerContraBots
{
    /** Mesma lista que o Laravel usa ao devolver um erro de validacao. */
    private const CAMPOS_SENSIVEIS = ['password', 'password_confirmation', 'current_password'];

    public const CHAVE_ERRO = 'guarda_bot';

    public function __construct(private readonly AvaliadorDeRisco $avaliador) {}

    public function handle(Request $request, Closure $next): Response
    {
        $modo = Modo::atual();

        if ($modo === Modo::Desligado) {
            return $next($request);
        }

        $veredito = $this->avaliador->avaliar($request);
        $bloquear = $modo === Modo::Ativo && $veredito->deveBloquear();

        if (! $veredito->limpo()) {
            $this->registrar($request, $veredito, $modo, $bloquear);
        }

        if ($bloquear) {
            return $this->recusar($request);
        }

        // Os campos da armadilha nao interessam a aplicacao: tira-los evita que
        // cheguem a um `$request->all()` ou a um mass assignment.
        $request->request->remove((string) config('guarda-bot.armadilha.campo'));
        $request->request->remove((string) config('guarda-bot.armadilha.campo_tempo'));

        return $next($request);
    }

    private function recusar(Request $request): Response
    {
        $mensagem = (string) config('guarda-bot.mensagem');

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensagem], Response::HTTP_FORBIDDEN);
        }

        return back()
            ->withInput($request->except(self::CAMPOS_SENSIVEIS))
            ->withErrors([self::CHAVE_ERRO => $mensagem]);
    }

    private function registrar(Request $request, Veredito $veredito, Modo $modo, bool $bloqueou): void
    {
        $canal = config('guarda-bot.log_canal');

        Log::channel(is_string($canal) && $canal !== '' ? $canal : null)->warning('guarda-bot: requisicao suspeita', [
            'modo' => $modo->value,
            'acao' => $bloqueou ? 'bloqueada' : 'permitida',
            'score' => $veredito->score,
            'motivos' => $veredito->motivos,
            'metodo' => $request->method(),
            'rota' => $request->route()?->getName() ?? $request->path(),
            'ip' => $request->ip(),
        ]);
    }
}
