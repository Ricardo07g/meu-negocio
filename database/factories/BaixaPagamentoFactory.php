<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaixaPagamento>
 */
class BaixaPagamentoFactory extends Factory
{
    protected $model = BaixaPagamento::class;

    public function definition(): array
    {
        return [
            'parcela_pagamento_id' => ParcelaPagamentoFactory::new(),
            'rede_id' => fn (array $attrs) => ParcelaPagamento::withoutGlobalScopes()->findOrFail($attrs['parcela_pagamento_id'])->rede_id,
            'empresa_id' => fn (array $attrs) => ParcelaPagamento::withoutGlobalScopes()->findOrFail($attrs['parcela_pagamento_id'])->empresa_id,
            'caixa_id' => null,
            'conta_id' => null,
            'valor' => fake()->randomFloat(2, 50, 500),
            'multa' => 0,
            'juros' => 0,
            'desconto' => 0,
            'forma_pagamento_id' => null,
            'forma_pagamento_nome' => 'Dinheiro',
            'data' => now(),
            'estornado_em' => null,
            'observacao' => null,
        ];
    }

    /** Recebimento desfeito: a baixa permanece, marcada (ADR-0011). */
    public function estornada(): static
    {
        return $this->state(fn () => ['estornado_em' => now()]);
    }
}
