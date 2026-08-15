<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('empresa.criar')
            : $this->user()->can('empresa.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],

            // Expediente da unidade: 7 linhas (uma por dia da semana). Vem junto
            // com o cadastro porque e a mesma tela — a unidade e o cadastro dela
            // mais o horario em que ela atende.
            'expediente' => ['nullable', 'array'],
            'expediente.*.ativo' => ['nullable', 'boolean'],
            'expediente.*.hora_inicio' => ['nullable', 'date_format:H:i'],
            'expediente.*.hora_fim' => ['nullable', 'date_format:H:i'],
        ];
    }
}
