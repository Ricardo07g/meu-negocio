<?php

namespace App\Modules\Usuario\Requests;

use App\Enums\PapelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtualizarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuario.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', Rule::unique('usuarios', 'email')->ignore($this->route('usuario'))],
            'password' => ['nullable', 'string', 'min:8'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'papel' => ['nullable', Rule::enum(PapelEnum::class)],
            'ativo' => ['nullable', 'boolean'],
        ];
    }
}
