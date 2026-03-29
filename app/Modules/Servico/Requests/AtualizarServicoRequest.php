<?php

namespace App\Modules\Servico\Requests;

use App\Enums\TipoServico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtualizarServicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('servico.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'duracao' => ['required', 'integer', 'min:1'],
            'valor' => ['required', 'numeric', 'min:0'],
            'tipo' => ['required', Rule::enum(TipoServico::class)],
            'qtd_sessoes' => ['nullable', 'required_if:tipo,pacote', 'integer', 'min:2'],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
