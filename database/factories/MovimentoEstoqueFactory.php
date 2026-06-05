<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoMovimentoEstoque;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimentoEstoque>
 */
class MovimentoEstoqueFactory extends Factory
{
    protected $model = MovimentoEstoque::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'produto_id' => fn (array $attrs) => ProdutoFactory::new()->state([
                'rede_id' => $attrs['rede_id'],
            ]),
            'tipo' => TipoMovimentoEstoque::Entrada,
            'quantidade' => fake()->numberBetween(1, 50),
        ];
    }

    public function entrada(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoEstoque::Entrada]);
    }

    public function saida(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoEstoque::Saida]);
    }

    public function ajuste(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimentoEstoque::Ajuste]);
    }
}
