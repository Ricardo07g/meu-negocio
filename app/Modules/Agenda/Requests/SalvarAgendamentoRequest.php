<?php

namespace App\Modules\Agenda\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarAgendamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            $this->isMethod('post') ? 'agendamento.criar' : 'agendamento.editar'
        );
    }

    public function rules(): array
    {
        $criando = $this->isMethod('post');
        $obrigatorio = $criando ? 'required' : 'nullable';

        return [
            'cliente_id' => [$obrigatorio, 'exists:clientes,id'],
            'servico_id' => [$obrigatorio, 'exists:servicos,id'],
            'atendente_id' => [$obrigatorio, 'exists:usuarios,id'],
            'inicio' => [$obrigatorio, 'date'],
            'fim' => ['nullable', 'date', 'after:inicio'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
