<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

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
        $ehAdmin = $this->input('papel') === 'Admin';
        $redeId = $this->user()?->rede_id;

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
            'papel' => [$criando ? 'required' : 'nullable', 'string', 'exists:roles,name'],
            'ativo' => ['nullable', 'boolean'],
            'atende' => ['nullable', 'boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remover_foto' => ['nullable', 'boolean'],
            // Admin nao precisa de pivot (acessa tudo). Nao-admin: array obrigatorio com >= 1 empresa da propria rede.
            'empresas' => [$ehAdmin ? 'nullable' : 'required', 'array', $ehAdmin ? 'nullable' : 'min:1'],
            'empresas.*' => [
                'integer',
                Rule::exists('empresas', 'id')->where(fn ($q) => $redeId ? $q->where('rede_id', $redeId) : $q),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'empresas.required' => 'Selecione ao menos uma empresa para o usuario.',
            'empresas.min' => 'Selecione ao menos uma empresa para o usuario.',
            'empresas.*.exists' => 'Empresa invalida ou fora da sua rede.',
        ];
    }
}
