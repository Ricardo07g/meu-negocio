<?php

namespace App\Modules\Tenant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('empresa.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
        ];
    }
}
