<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Cliente\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'rede_id' => RedeFactory::new(),
            'nome' => fake('pt_BR')->name(),
            'telefone' => fake('pt_BR')->cellphoneNumber(false),
            'telefone_whatsapp' => fake()->boolean(),
            'email' => fake()->unique()->safeEmail(),
            'data_nascimento' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'cpf' => fake('pt_BR')->cpf(false),
            'sexo' => fake()->randomElement(['M', 'F']),
            'cep' => fake('pt_BR')->postcode(),
            'estado' => fake('pt_BR')->stateAbbr(),
            'cidade' => fake('pt_BR')->city(),
            'bairro' => fake('pt_BR')->word(),
            'logradouro' => fake('pt_BR')->streetName(),
            'numero' => (string) fake()->numberBetween(1, 9999),
            'complemento' => null,
            'observacoes' => null,
        ];
    }
}
