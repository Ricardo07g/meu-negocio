<?php

namespace App\Modules\Pagamento\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelarParcelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
