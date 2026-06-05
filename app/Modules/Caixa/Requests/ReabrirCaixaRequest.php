<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReabrirCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.editar');
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da reabertura.',
            'motivo.min' => 'O motivo deve ter ao menos 5 caracteres.',
        ];
    }
}
