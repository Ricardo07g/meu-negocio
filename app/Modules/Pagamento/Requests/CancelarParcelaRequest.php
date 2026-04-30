<?php

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request compartilhado entre cancelar parcela de Pagamento e de
 * Despesa. Permissao depende da rota — ver SalvarBaixaParcelaRequest.
 */
class CancelarParcelaRequest extends FormRequest
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
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
