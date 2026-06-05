<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenant\Models\Empresa;
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
            'rede_id' => RedeFactory::new(),
            'nome' => fake('pt_BR')->company(),
            'documento' => null,
            'telefone' => fake('pt_BR')->cellphoneNumber(false),
            'email' => fake()->safeEmail(),
        ];
    }
}
