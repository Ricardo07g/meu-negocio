<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Self-service (Meu Perfil): o usuario so mexe no proprio 2FA. */
class ConfirmarDoisFatoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Informe o código de 6 dígitos do aplicativo.',
        ];
    }
}
