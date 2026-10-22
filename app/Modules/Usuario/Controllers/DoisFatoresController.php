<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Usuario\Requests\{ConfirmarDoisFatoresRequest, ExigirDoisFatoresRequest, SenhaDoisFatoresRequest};
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * 2FA (ADR-0022): self-service na aba Seguranca do Meu Perfil + as duas
 * acoes do Admin (exigir na rede e redefinir o de um usuario).
 */
class DoisFatoresController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private DoisFatoresService $service,
    ) {}

    public function iniciar(Request $request): RedirectResponse
    {
        try {
            $this->service->iniciar($request->user());

            return $this->voltarParaSeguranca();
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao iniciar a verificação em duas etapas');
        }
    }

    public function confirmar(ConfirmarDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $codigos = $this->service->confirmar($request->user(), (string) $request->input('codigo'));

            return $this->voltarParaSeguranca()
                ->with('sucesso', 'Verificação em duas etapas ativada.')
                ->with('codigos_recuperacao', $codigos);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao confirmar a verificação em duas etapas');
        }
    }

    public function regenerarCodigos(SenhaDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $codigos = $this->service->regenerarCodigos($request->user());

            return $this->voltarParaSeguranca()
                ->with('sucesso', 'Novos códigos gerados. Os anteriores deixaram de valer.')
                ->with('codigos_recuperacao', $codigos);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao gerar códigos de recuperação');
        }
    }

    public function desativar(SenhaDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $this->service->desativar($request->user());

            return $this->voltarParaSeguranca()->with('sucesso', 'Verificação em duas etapas desativada.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao desativar a verificação em duas etapas');
        }
    }

    public function exigir(ExigirDoisFatoresRequest $request): RedirectResponse
    {
        try {
            $rede = $request->user()->rede;
            $this->authorize('configurarSeguranca', $rede);

            $exigir = $request->boolean('exigir');
            $this->service->definirExigencia($rede, $exigir);

            return redirect()->route('usuarios.index')->with(
                'sucesso',
                $exigir
                    ? 'A verificação em duas etapas agora é obrigatória na rede.'
                    : 'A verificação em duas etapas voltou a ser opcional.'
            );
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao alterar a exigência de verificação em duas etapas');
        }
    }

    public function redefinir(Usuario $usuario): RedirectResponse
    {
        try {
            $this->authorize('redefinirDoisFatores', $usuario);
            $this->service->redefinir($usuario);

            return redirect()->route('usuarios.edit', $usuario)
                ->with('sucesso', 'Verificação em duas etapas redefinida. O usuário pode configurá-la de novo no próximo acesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao redefinir a verificação em duas etapas');
        }
    }

    private function voltarParaSeguranca(): RedirectResponse
    {
        return redirect()->route('perfil.index', ['aba' => 'seguranca']);
    }
}
