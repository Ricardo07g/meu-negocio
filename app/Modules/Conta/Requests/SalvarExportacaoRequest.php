<?php

declare(strict_types=1);

namespace App\Modules\Conta\Requests;

use App\Enums\FormatoExportacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarExportacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('conta.ver');
    }

    public function rules(): array
    {
        return [
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
            'formato' => ['required', Rule::enum(FormatoExportacao::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'de.required' => 'Informe a data inicial do período.',
            'ate.required' => 'Informe a data final do período.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'formato.required' => 'Escolha o formato do arquivo.',
        ];
    }
}
