<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Models\Rede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'rede_id' => Rede::factory(),
            'nome' => fake('pt_BR')->company(),
            'documento' => null,
            'telefone' => fake('pt_BR')->cellphoneNumber(false),
            'email' => fake()->safeEmail(),
        ];
    }
}
