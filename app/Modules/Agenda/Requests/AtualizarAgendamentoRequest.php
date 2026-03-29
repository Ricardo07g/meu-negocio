<?php

namespace App\Modules\Agenda\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarAgendamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agendamento.editar');
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'servico_id' => ['nullable', 'exists:servicos,id'],
            'profissional_id' => ['nullable', 'exists:profissionais,id'],
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date', 'after:inicio'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
