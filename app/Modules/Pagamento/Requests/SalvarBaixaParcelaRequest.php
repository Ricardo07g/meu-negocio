<?php

declare(strict_types=1);

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request compartilhado entre baixa de parcela de Pagamento (contas a
 * receber) e baixa de parcela de Despesa (contas a pagar). A permissao
 * exigida varia conforme a rota:
 *
 *  - `parcelas-pagamento.baixa`  -> exige `pagamento.editar`
 *  - `parcelas-despesa.baixa`    -> exige `despesa.editar`
 *
 * Sem essa distincao um Profissional com permissao apenas de receber
 * conseguiria pagar despesas e vice-versa (FECH-010).
 */
class SalvarBaixaParcelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if (! $usuario) {
            return false;
        }

        $permissao = $this->routeIs('parcelas-despesa.*')
            ? 'despesa.editar'
            : 'pagamento.editar';

        return $usuario->can($permissao);
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'min:0.01'],
            'multa' => ['nullable', 'numeric', 'min:0'],
            'juros' => ['nullable', 'numeric', 'min:0'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'forma_pagamento' => ['required', 'string'],
            'observacao' => ['nullable', 'string'],
        ];
    }
}
