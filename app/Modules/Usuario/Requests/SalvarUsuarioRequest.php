<?php

namespace App\Modules\Usuario\Requests;

use App\Enums\PapelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            $this->isMethod('post') ? 'usuario.criar' : 'usuario.editar'
        );
    }

    public function rules(): array
    {
        $criando = $this->isMethod('post');
        $usuarioId = $this->route('usuario');

        return [
            'nome' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'email',
                $criando
                    ? Rule::unique('usuarios', 'email')
                    : Rule::unique('usuarios', 'email')->ignore($usuarioId),
            ],
            'password' => [$criando ? 'required' : 'nullable', 'string', 'min:8'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'papel' => [$criando ? 'required' : 'nullable', Rule::enum(PapelEnum::class)],
            'ativo' => ['nullable', 'boolean'],
            'atende' => ['nullable', 'boolean'],
        ];
    }
}
