<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoMovimentoCaixa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimentoCaixa>
 */
class MovimentoCaixaFactory extends Factory
{
    protected $model = MovimentoCaixa::class;

    public function definition(): array
    {
        return [
            'caixa_id' => CaixaFactory::new(),
            'tipo' => TipoMovimentoCaixa::Entrada,
            'valor' => fake()->randomFloat(2, 10, 500),
            'descricao' => fake('pt_BR')->sentence(3),
            'forma_pagamento_nome' => 'Dinheiro',
            'baixa_pagamento_id' => null,
            'baixa_despesa_id' => null,
        ];
    }

    public function entrada(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoCaixa::Entrada]);
    }

    public function saida(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoCaixa::Saida]);
    }

    public function sangria(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoCaixa::Sangria]);
    }

    public function reforco(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoCaixa::Reforco]);
    }
}
