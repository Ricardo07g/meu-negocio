<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\FormaPagamento\Models\{FormaPagamento, FormaPagamentoTaxa};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormaPagamentoTaxa>
 */
class FormaPagamentoTaxaFactory extends Factory
{
    protected $model = FormaPagamentoTaxa::class;

    public function definition(): array
    {
        return [
            'forma_pagamento_id' => FormaPagamentoFactory::new()->credito(),
            'rede_id' => fn (array $attrs) => FormaPagamento::withoutGlobalScopes()->findOrFail($attrs['forma_pagamento_id'])->rede_id,
            'parcela_min' => 1,
            'parcela_max' => 1,
            'taxa_percentual' => 3.20,
        ];
    }
}
