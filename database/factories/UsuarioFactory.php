<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Models\Rede;
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
            'rede_id' => Rede::factory(),
            'empresa_id' => Empresa::factory(),
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
