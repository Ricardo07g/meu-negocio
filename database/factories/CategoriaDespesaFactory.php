<?php

namespace Database\Factories;

use App\Modules\Despesa\Models\CategoriaDespesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaDespesa>
 */
class CategoriaDespesaFactory extends Factory
{
    protected $model = CategoriaDespesa::class;

    public function definition(): array
    {
        return [
            'rede_id' => RedeFactory::new(),
            'descricao' => fake('pt_BR')->words(2, true),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
