<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Requests;

use App\Modules\Tenant\Models\Fatura;
use Illuminate\Foundation\Http\FormRequest;

class RenovarTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('renovarTrial', Fatura::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // O teste e da unidade: a renovacao precisa dizer QUAL licenca reabre.
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }
}
