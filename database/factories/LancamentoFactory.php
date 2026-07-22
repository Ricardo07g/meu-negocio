<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoLancamento;
use App\Modules\Conta\Models\{Conta, Lancamento};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lancamento>
 */
class LancamentoFactory extends Factory
{
    protected $model = Lancamento::class;

    public function definition(): array
    {
        return [
            'conta_id' => ContaFactory::new(),
            'rede_id' => fn (array $attrs) => Conta::withoutGlobalScopes()->findOrFail($attrs['conta_id'])->rede_id,
            'empresa_id' => fn (array $attrs) => Conta::withoutGlobalScopes()->findOrFail($attrs['conta_id'])->empresa_id,
            'caixa_id' => null,
            'tipo' => TipoLancamento::Credito,
            'categoria' => 'movimento',
            'valor' => fake()->randomFloat(2, 10, 500),
            'data' => now()->toDateString(),
            'descricao' => 'Lançamento de teste',
            'forma_pagamento_nome' => null,
            'baixa_pagamento_id' => null,
            'baixa_despesa_id' => null,
        ];
    }

    public function credito(): static
    {
        return $this->state(fn () => ['tipo' => TipoLancamento::Credito]);
    }

    public function debito(): static
    {
        return $this->state(fn () => ['tipo' => TipoLancamento::Debito]);
    }
}
