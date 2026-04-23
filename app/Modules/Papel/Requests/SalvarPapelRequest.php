<?php

namespace App\Modules\Papel\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarPapelRequest extends FormRequest
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
        $papelId = $this->route('papel')?->id ?? null;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                $criando
                    ? Rule::unique('roles', 'name')
                    : Rule::unique('roles', 'name')->ignore($papelId),
            ],
            'permissoes' => ['nullable', 'array'],
            'permissoes.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
