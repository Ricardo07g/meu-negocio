<?php

declare(strict_types=1);

namespace App\Modules\Conta\Requests;

use App\Enums\TipoConta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('conta.criar')
            : $this->user()->can('conta.editar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::enum(TipoConta::class)],
            'saldo_inicial' => ['nullable', 'numeric', 'min:-9999999', 'max:99999999'],
            'ativo' => ['nullable', 'boolean'],
            'eh_caixa_padrao' => ['nullable', 'boolean'],
            'eh_destino_recebivel_padrao' => ['nullable', 'boolean'],
            'instituicao' => ['nullable', 'string', 'max:100'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero' => ['nullable', 'string', 'max:30'],
        ];
    }
}
