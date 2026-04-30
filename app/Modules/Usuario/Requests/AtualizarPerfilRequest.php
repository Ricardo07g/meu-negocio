<?php

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtualizarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'email',
                Rule::unique('usuarios', 'email')->ignore($this->user()->id),
            ],
        ];
    }
}
