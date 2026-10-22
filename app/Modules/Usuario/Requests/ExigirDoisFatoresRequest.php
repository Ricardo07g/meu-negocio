<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExigirDoisFatoresRequest extends FormRequest
{
    /** A alcada (Admin da propria rede) e da RedePolicy, checada no controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'exigir' => ['required', 'boolean'],
        ];
    }
}
