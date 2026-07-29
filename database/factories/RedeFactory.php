<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusRede;
use App\Modules\Tenant\Models\Rede;
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
            'status' => StatusRede::Ativa,
        ];
    }
}
