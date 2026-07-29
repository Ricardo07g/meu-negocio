<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenant\Models\{Empresa, Plano};
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
            // Licenca Pro por padrao: e o que o registro real entrega (trial no Pro) e o que
            // mantem estoque/financeiro disponiveis nos testes de fluxo.
            'plano_id' => Plano::firstWhere('slug', Plano::PRO)?->id ?? PlanoFactory::new(),
            'nome' => fake('pt_BR')->company(),
            'documento' => null,
            'telefone' => fake('pt_BR')->cellphoneNumber(false),
            'email' => fake()->safeEmail(),
        ];
    }
}
