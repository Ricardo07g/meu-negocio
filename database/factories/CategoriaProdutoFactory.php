<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Produto\Models\CategoriaProduto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaProduto>
 */
class CategoriaProdutoFactory extends Factory
{
    protected $model = CategoriaProduto::class;

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
