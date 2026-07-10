<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('cliente.criar')
            : $this->user()->can('cliente.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:200'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'telefone_whatsapp' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email'],
            'data_nascimento' => ['nullable', 'date_format:d/m/Y'],
            'cpf' => ['nullable', 'string', 'size:14'],
            'sexo' => ['nullable', 'in:M,F,outro'],
            'cep' => ['nullable', 'string', 'size:9'],
            'estado' => ['nullable', 'string', 'size:2'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'logradouro' => ['nullable', 'string', 'max:200'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remover_foto' => ['nullable', 'boolean'],
        ];
    }
}
