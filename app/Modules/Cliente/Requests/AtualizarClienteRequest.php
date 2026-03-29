<?php

namespace App\Modules\Cliente\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cliente.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
