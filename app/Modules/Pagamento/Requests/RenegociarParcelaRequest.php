<?php

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenegociarParcelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
