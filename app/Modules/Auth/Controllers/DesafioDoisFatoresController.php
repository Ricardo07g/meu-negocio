<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\DesafioDoisFatoresRequest;
use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Auth\Support\PendenciaDoisFatores;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Segunda etapa do login (ADR-0022). O usuario chega aqui com a senha ja
 * conferida, mas DESLOGADO: so existe uma pendencia na sessao.
 */
class DesafioDoisFatoresController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private DoisFatoresService $service,
    ) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        try {
            if ($this->pendencia($request)->usuario() === null) {
                return $this->voltarAoLogin($request, 'Sua verificação expirou. Entre com email e senha novamente.');
            }

            return view('auth::dois-fatores');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar a verificação em duas etapas');
        }
    }

    public function verificar(DesafioDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $pendencia = $this->pendencia($request);
            $usuario = $pendencia->usuario();

            if ($usuario === null) {
                return $this->voltarAoLogin($request, 'Sua verificação expirou. Entre com email e senha novamente.');
            }

            $recuperacao = $request->filled('codigo_recuperacao');

            $valido = $recuperacao
                ? $this->service->usarCodigoRecuperacao($usuario, (string) $request->input('codigo_recuperacao'))
                : $this->service->verificarCodigo($usuario, (string) $request->input('codigo'));

            if (! $valido) {
                if (! $pendencia->registrarErro()) {
                    return $this->voltarAoLogin($request, 'Muitas tentativas inválidas. Entre com email e senha novamente.');
                }

                return back()->withErrors([$recuperacao ? 'codigo_recuperacao' : 'codigo' => 'Código inválido.']);
            }

            $pendencia->descartar();
            Auth::login($usuario);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao validar a verificação em duas etapas');
        }
    }

    private function pendencia(Request $request): PendenciaDoisFatores
    {
        return new PendenciaDoisFatores($request->session());
    }

    private function voltarAoLogin(Request $request, string $mensagem): RedirectResponse
    {
        $this->pendencia($request)->descartar();

        return redirect()->route('login')->with('erro', $mensagem);
    }
}
