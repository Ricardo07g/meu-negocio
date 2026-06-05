<?php

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
            'plano_id' => ['required', 'integer', 'exists:planos,id'],
        ];
    }
}
