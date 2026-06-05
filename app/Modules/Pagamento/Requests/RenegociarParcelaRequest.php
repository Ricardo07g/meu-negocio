<?php

declare(strict_types=1);

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request compartilhado entre renegociar parcela de Pagamento e de
 * Despesa. Permissao depende da rota — ver SalvarBaixaParcelaRequest.
 */
class RenegociarParcelaRequest extends FormRequest
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
            'data_vencimento' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacao' => ['nullable', 'string'],
        ];
    }
}
