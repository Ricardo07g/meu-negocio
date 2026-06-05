<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FecharCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.editar');
    }

    public function rules(): array
    {
        return [
            'saldo_fechamento' => ['required', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string'],
        ];
    }
}
