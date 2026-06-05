<?php

declare(strict_types=1);

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

        // ME-010: empresa_id e obrigatorio na criacao quando ha mais de uma
        // empresa selecionada na sessao; com 1 empresa o EmpresaTrait resolve.
        $empresasAtuais = (array) session('empresas_atuais', []);
        $exigeEmpresa = $criando && count($empresasAtuais) > 1;

        return [
            'empresa_id' => [
                $exigeEmpresa ? 'required' : 'nullable',
                'integer',
                $exigeEmpresa ? 'in:'.implode(',', $empresasAtuais) : 'nullable',
            ],
            'cliente_id' => [$obrigatorio, 'exists:clientes,id'],
            'servico_id' => [$obrigatorio, 'exists:servicos,id'],
            'atendente_id' => [$obrigatorio, 'exists:usuarios,id'],
            'inicio' => [$obrigatorio, 'date'],
            'fim' => ['nullable', 'date', 'after:inicio'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
