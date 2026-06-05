<?php

namespace Database\Factories;

use App\Enums\StatusCaixa;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caixa>
 */
class CaixaFactory extends Factory
{
    protected $model = Caixa::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'usuario_id' => fn (array $attrs) => UsuarioFactory::new()->state([
                'rede_id' => $attrs['rede_id'],
                'empresa_id' => $attrs['empresa_id'],
            ]),
            'data' => now()->format('Y-m-d'),
            'saldo_abertura' => fake()->randomFloat(2, 0, 500),
            'saldo_fechamento' => null,
            'status' => StatusCaixa::Aberto,
            'observacao' => null,
            'fechado_em' => null,
            'fechado_por' => null,
        ];
    }

    public function aberto(): static
    {
        return $this->state(fn () => [
            'status' => StatusCaixa::Aberto,
            'saldo_fechamento' => null,
            'fechado_em' => null,
            'fechado_por' => null,
        ]);
    }

    public function fechado(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => StatusCaixa::Fechado,
            'saldo_fechamento' => $attrs['saldo_abertura'] ?? 0,
            'fechado_em' => now(),
        ]);
    }
}
