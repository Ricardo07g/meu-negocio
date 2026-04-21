<?php

namespace App\Modules\Despesa\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarDespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('despesa.criar')
            : $this->user()->can('despesa.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'categoria_despesa_id' => ['nullable', 'integer', 'exists:categorias_despesa,id'],
            'fornecedor_nome' => ['nullable', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:80'],
            'observacoes' => ['nullable', 'string'],
            'data_emissao' => ['required', 'date'],
            'data_vencimento' => ['required', 'date', 'after_or_equal:data_emissao'],
            'competencia' => ['required', 'date'],
            'parcelar' => ['sometimes', 'boolean'],
            'numero_parcelas' => ['nullable', 'integer', 'min:2', 'max:60', 'required_if:parcelar,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parcelar' => filter_var($this->input('parcelar'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
