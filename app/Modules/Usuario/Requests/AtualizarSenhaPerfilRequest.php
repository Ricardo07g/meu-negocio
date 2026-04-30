<?php

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AtualizarSenhaPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'senha_atual' => [
                'required',
                'string',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if (! Hash::check((string) $valor, (string) $this->user()->password)) {
                        $falhar('A senha atual informada está incorreta.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed', Rule::notIn([$this->input('senha_atual')])],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'A confirmação da nova senha não confere.',
            'password.not_in' => 'A nova senha precisa ser diferente da atual.',
        ];
    }
}
