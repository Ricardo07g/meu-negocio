<?php

namespace App\Modules\Tenant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CriarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('empresa.criar');
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
