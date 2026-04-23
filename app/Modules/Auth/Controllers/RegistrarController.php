<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\RegistrarRequest;
use App\Modules\Tenant\DTOs\CriarRedeData;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Tenant\Services\RedeService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistrarController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private RedeService $redeService,
    ) {}

    public function showRegistrationForm(): View|RedirectResponse
    {
        try {
            return view('auth::registrar');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar página de registro');
        }
    }

    public function register(RegistrarRequest $request): RedirectResponse
    {
        try {
            $rede = $this->redeService->criar(
                new CriarRedeData(nome: $request->empresa),
                new UsuarioData(
                    nome: $request->nome,
                    email: $request->email,
                    password: $request->password,
                )
            );

            $usuario = $rede->getRelation('usuarioCriado');
            Auth::login($usuario);

            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao registrar conta');
        }
    }
}
