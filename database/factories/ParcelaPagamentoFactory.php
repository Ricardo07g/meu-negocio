<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusParcela;
use App\Modules\Pagamento\Models\{Pagamento, ParcelaPagamento};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParcelaPagamento>
 */
class ParcelaPagamentoFactory extends Factory
{
    protected $model = ParcelaPagamento::class;

    public function definition(): array
    {
        return [
            'pagamento_id' => PagamentoFactory::new(),
            'rede_id' => fn (array $attrs) => Pagamento::find($attrs['pagamento_id'])->rede_id,
            'empresa_id' => fn (array $attrs) => Pagamento::find($attrs['pagamento_id'])->empresa_id,
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
