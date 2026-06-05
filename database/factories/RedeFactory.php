<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusRede;
use App\Modules\Tenant\Models\{Plano, Rede};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rede>
 */
class RedeFactory extends Factory
{
    protected $model = Rede::class;

    public function definition(): array
    {
        return [
            'nome' => fake('pt_BR')->company(),
            'plano_id' => Plano::firstWhere('nome', 'free')?->id ?? PlanoFactory::new(),
            'status' => StatusRede::Ativa,
        ];
    }
}
