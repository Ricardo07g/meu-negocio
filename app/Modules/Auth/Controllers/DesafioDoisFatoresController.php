<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\DesafioDoisFatoresRequest;
use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Segunda etapa do login (ADR-0022). O usuario chega aqui com a senha ja
 * conferida, mas DESLOGADO: so existe uma pendencia na sessao, com prazo.
 */
class DesafioDoisFatoresController extends Controller
{
    use TratamentoErros;

    private const CHAVE = 'login.dois_fatores';

    private const MINUTOS_PARA_RESPONDER = 5;

    public function __construct(
        private DoisFatoresService $service,
    ) {}

    public static function registrarPendencia(Request $request, Usuario $usuario): void
    {
        $request->session()->put(self::CHAVE, [
            'id' => $usuario->getKey(),
            'expira' => now()->addMinutes(self::MINUTOS_PARA_RESPONDER)->getTimestamp(),
        ]);
    }

    public function mostrar(Request $request): View|RedirectResponse
    {
        try {
            if ($this->usuarioPendente($request) === null) {
                return $this->voltarAoLogin($request);
            }

            return view('auth::dois-fatores');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar a verificação em duas etapas');
        }
    }

    public function verificar(DesafioDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $usuario = $this->usuarioPendente($request);

            if ($usuario === null) {
                return $this->voltarAoLogin($request);
            }

            $recuperacao = $request->filled('codigo_recuperacao');

            $valido = $recuperacao
                ? $this->service->usarCodigoRecuperacao($usuario, (string) $request->input('codigo_recuperacao'))
                : $this->service->verificarCodigo($usuario, (string) $request->input('codigo'));

            if (! $valido) {
                $campo = $recuperacao ? 'codigo_recuperacao' : 'codigo';

                return back()->withErrors([$campo => 'Código inválido.']);
            }

            $request->session()->forget(self::CHAVE);
            Auth::login($usuario);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao validar a verificação em duas etapas');
        }
    }

    /**
     * Pendencia valida = dentro do prazo, usuario ainda ativo e com 2FA ainda
     * ligado (o Admin pode ter redefinido no meio do caminho).
     */
    private function usuarioPendente(Request $request): ?Usuario
    {
        $pendencia = $request->session()->get(self::CHAVE);

        if (! is_array($pendencia) || ($pendencia['expira'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $usuario = Usuario::find($pendencia['id'] ?? null);

        return ($usuario !== null && $usuario->ativo && $usuario->temDoisFatoresAtivo()) ? $usuario : null;
    }

    private function voltarAoLogin(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CHAVE);

        return redirect()->route('login')->with('erro', 'Sua verificação expirou. Entre com email e senha novamente.');
    }
}
