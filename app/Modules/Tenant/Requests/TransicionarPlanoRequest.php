<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Requests;

use App\Modules\Tenant\Models\Fatura;
use Illuminate\Foundation\Http\FormRequest;

class TransicionarPlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transicionar', Fatura::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // A licenca e da unidade: a troca precisa dizer QUAL unidade muda de plano.
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'plano_id' => ['required', 'integer', 'exists:planos,id'],
        ];
    }
}
