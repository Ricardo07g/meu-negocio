<?php

namespace Database\Factories;

use App\Modules\Produto\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produto>
 */
class ProdutoFactory extends Factory
{
    protected $model = Produto::class;

    public function definition(): array
    {
        $custo = fake()->randomFloat(2, 5, 100);

        return [
            'rede_id' => RedeFactory::new(),
            'nome' => fake('pt_BR')->words(2, true),
            'codigo' => fake()->unique()->bothify('PRD-####'),
            'codigo_barras' => fake()->ean13(),
            'descricao' => fake('pt_BR')->optional()->sentence(),
            'categoria_produto_id' => null,
            'quantidade' => fake()->numberBetween(0, 100),
            'valor_custo' => $custo,
            'valor_venda' => round($custo * 1.8, 2),
            'estoque_minimo' => fake()->numberBetween(0, 10),
            'unidade' => fake()->randomElement(['un', 'cx', 'kg', 'ml']),
            'ativo' => true,
            'observacoes' => null,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
