<?php

declare(strict_types=1);

namespace App\Modules\Conta\Requests;

use App\Enums\TipoConta;
use App\Modules\Conta\Models\Conta;
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
        // A conta Caixa do sistema so pode ser renomeada (nem tipo, nem flags, nem inativar).
        if ($this->editandoContaCaixa()) {
            return ['nome' => ['required', 'string', 'max:100']];
        }

        // O lojista so cria/edita contas Banco/Carteira; a gaveta (Caixa) nasce do seed.
        return [
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::in([TipoConta::Banco->value, TipoConta::Carteira->value])],
            'saldo_inicial' => ['nullable', 'numeric', 'min:-9999999', 'max:99999999'],
            'ativo' => ['nullable', 'boolean'],
            'instituicao' => ['nullable', 'string', 'max:100'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function editandoContaCaixa(): bool
    {
        $conta = $this->route('conta');

        return $conta instanceof Conta && $conta->ehProtegida();
    }
}
