<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

/**
 * Confirmar = o momento em que o 2FA passa a valer: pede o codigo do app e a
 * senha. A senha tambem serve ao `logoutOtherDevices`, que derruba as sessoes
 * abertas antes da ativacao (ADR-0022). Erros no bag `confirmar`.
 */
class ConfirmarDoisFatoresRequest extends SenhaDoisFatoresRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            ...parent::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Informe o código de 6 dígitos do aplicativo.',
            ...parent::messages(),
        ];
    }
}
