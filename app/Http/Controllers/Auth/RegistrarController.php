<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrarRequest;
use App\DTO\Rede\CriarRedeData;
use App\DTO\Usuario\CriarUsuarioData;
use App\Services\RedeService;
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
            return view('auth.registrar');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar página de registro');
        }
    }

    public function register(RegistrarRequest $request): RedirectResponse
    {
        try {
            $rede = $this->redeService->criar(
                new CriarRedeData(nome: $request->empresa),
                new CriarUsuarioData(
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
