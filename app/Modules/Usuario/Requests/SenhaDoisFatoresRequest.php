<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Desativar o 2FA ou gerar novos codigos de recuperacao exige a senha atual:
 * uma sessao esquecida aberta nao basta para derrubar o segundo fator.
 *
 * O campo e `senha_2fa`, e nao `senha_atual`, para o erro abrir a aba
 * Seguranca e nao a aba Alterar senha do perfil.
 */
class SenhaDoisFatoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'senha_2fa' => [
                'required',
                'string',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if (! Hash::check((string) $valor, (string) $this->user()->password)) {
                        $falhar('A senha informada está incorreta.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'senha_2fa.required' => 'Informe sua senha para confirmar.',
        ];
    }
}
