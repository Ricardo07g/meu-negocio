<?php

namespace Database\Factories;

use App\Enums\StatusParcela;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Despesa\Models\ParcelaDespesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParcelaDespesa>
 */
class ParcelaDespesaFactory extends Factory
{
    protected $model = ParcelaDespesa::class;

    public function definition(): array
    {
        return [
            'despesa_id' => DespesaFactory::new(),
            'rede_id' => fn (array $attrs) => Despesa::find($attrs['despesa_id'])->rede_id,
            'empresa_id' => fn (array $attrs) => Despesa::find($attrs['despesa_id'])->empresa_id,
            'numero' => 1,
            'total' => 1,
            'valor' => fake()->randomFloat(2, 50, 500),
            'valor_pago' => 0,
            'data_vencimento' => now()->addMonth()->format('Y-m-d'),
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
            'forma_pagamento' => null,
            'status' => StatusParcela::Pendente,
            'observacao' => null,
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn () => [
            'status' => StatusParcela::Pendente,
            'valor_pago' => 0,
        ]);
    }

    public function paga(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => StatusParcela::Pago,
            'valor_pago' => $attrs['valor'] ?? fake()->randomFloat(2, 50, 500),
        ]);
    }
}
