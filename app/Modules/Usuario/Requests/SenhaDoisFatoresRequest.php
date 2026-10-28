<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Confirmar (ligar), desativar o 2FA ou gerar novos codigos exige a senha atual:
 * uma sessao esquecida aberta nao basta para mexer no segundo fator.
 *
 * O campo e `senha_2fa`, e nao `senha_atual`, para o erro abrir a aba
 * Seguranca e nao a aba Alterar senha do perfil. Cada acao tem o proprio
 * error bag — os forms ficam lado a lado e o erro precisa aparecer no certo.
 */
class SenhaDoisFatoresRequest extends FormRequest
{
    /** @var array<string, string> rota => error bag */
    private const BAGS = [
        'perfil.dois-fatores.confirmar' => 'confirmar',
        'perfil.dois-fatores.codigos' => 'codigos',
        'perfil.dois-fatores.desativar' => 'desativar',
    ];

    protected function prepareForValidation(): void
    {
        $this->errorBag = self::BAGS[$this->route()?->getName()] ?? 'default';
    }

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
