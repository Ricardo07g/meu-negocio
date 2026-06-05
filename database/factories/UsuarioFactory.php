<?php

namespace Database\Factories;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'rede_id' => RedeFactory::new(),
            'empresa_id' => fn (array $attrs) => EmpresaFactory::new()->state(['rede_id' => $attrs['rede_id']]),
            'nome' => fake('pt_BR')->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'ativo' => true,
            'atende' => false,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function atendente(): static
    {
        return $this->state(fn () => ['atende' => true]);
    }
}
