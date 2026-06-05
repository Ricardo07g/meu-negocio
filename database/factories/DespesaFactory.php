<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\{CondicaoPagamento, StatusDespesa};
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Despesa>
 */
class DespesaFactory extends Factory
{
    protected $model = Despesa::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'categoria_despesa_id' => null,
            'nome' => fake('pt_BR')->words(3, true),
            'fornecedor_nome' => fake('pt_BR')->company(),
            'documento' => fake()->optional()->bothify('NF-#####'),
            'observacoes' => null,
            'valor_total' => fake()->randomFloat(2, 50, 2000),
            'condicao_pagamento' => CondicaoPagamento::AVista,
            'forma_recebimento_prazo' => null,
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
            'data_emissao' => now()->format('Y-m-d'),
            'status' => StatusDespesa::Pendente,
        ];
    }

    public function paga(): static
    {
        return $this->state(fn () => ['status' => StatusDespesa::Paga]);
    }

    public function aPrazo(): static
    {
        return $this->state(fn () => ['condicao_pagamento' => CondicaoPagamento::APrazo]);
    }
}
