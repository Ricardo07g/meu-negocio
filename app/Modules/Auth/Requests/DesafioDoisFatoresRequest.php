<?php

declare(strict_types=1);

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DesafioDoisFatoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:20', 'required_without:codigo_recuperacao'],
            'codigo_recuperacao' => ['nullable', 'string', 'max:30', 'required_without:codigo'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required_without' => 'Informe o código do aplicativo.',
            'codigo_recuperacao.required_without' => 'Informe um código de recuperação.',
        ];
    }
}
