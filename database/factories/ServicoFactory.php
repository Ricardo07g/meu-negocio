<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoServico;
use App\Modules\Servico\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servico>
 */
class ServicoFactory extends Factory
{
    protected $model = Servico::class;

    public function definition(): array
    {
        return [
            'rede_id' => RedeFactory::new(),
            'nome' => fake('pt_BR')->words(2, true),
            'duracao' => fake()->randomElement([30, 45, 60, 90]),
            'valor' => fake()->randomFloat(2, 50, 500),
            'tipo' => TipoServico::Unico,
            'qtd_etapas' => null,
            'descricao' => fake('pt_BR')->optional()->sentence(),
        ];
    }

    public function avulso(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoServico::Unico,
            'qtd_etapas' => null,
        ]);
    }

    public function etapas(int $qtd = 10): static
    {
        return $this->state(fn () => [
            'tipo' => TipoServico::Etapas,
            'qtd_etapas' => $qtd,
        ]);
    }
}
