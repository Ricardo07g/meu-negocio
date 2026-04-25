<?php

namespace App\Modules\PerfilAcesso\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarPerfilAcessoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            $this->isMethod('post') ? 'papel.criar' : 'papel.editar'
        );
    }

    public function rules(): array
    {
        $criando = $this->isMethod('post');
        $perfilId = $this->route('perfil_acesso')?->id ?? null;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                $criando
                    ? Rule::unique('roles', 'name')
                    : Rule::unique('roles', 'name')->ignore($perfilId),
            ],
            'permissoes' => ['nullable', 'array'],
            'permissoes.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
