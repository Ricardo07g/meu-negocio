<?php

namespace App\Modules\Usuario\Requests;

use App\Enums\PapelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuario.criar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'papel' => ['required', Rule::enum(PapelEnum::class)],
        ];
    }
}
