<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenant\Models\Plano;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plano>
 */
class PlanoFactory extends Factory
{
    protected $model = Plano::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->word(),
            'preco_mensal' => fake()->randomFloat(2, 0, 199),
            'descricao' => fake('pt_BR')->optional()->sentence(),
            'max_empresas' => 1,
            'max_usuarios' => 2,
            'tem_estoque' => false,
            'tem_financeiro' => false,
        ];
    }
}
