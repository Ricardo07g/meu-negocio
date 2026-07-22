<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Caixa\Models\Recebivel;
use App\Modules\Conta\Models\Conta;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recebivel>
 */
class RecebivelFactory extends Factory
{
    protected $model = Recebivel::class;

    public function definition(): array
    {
        $bruto = fake()->randomFloat(2, 50, 500);
        $taxa = 3.20;

        return [
            'forma_pagamento_id' => FormaPagamentoFactory::new()->credito(),
            'rede_id' => fn (array $attrs) => FormaPagamento::withoutGlobalScopes()->findOrFail($attrs['forma_pagamento_id'])->rede_id,
            'empresa_id' => fn (array $attrs) => FormaPagamento::withoutGlobalScopes()->findOrFail($attrs['forma_pagamento_id'])->empresa_id,
            // Conta destino do recebivel: a conta banco padrao da empresa (se houver).
            'conta_id' => fn (array $attrs) => Conta::withoutGlobalScopes()
                ->where('empresa_id', $attrs['empresa_id'])
                ->where('eh_destino_recebivel_padrao', true)
                ->value('id'),
            'baixa_pagamento_id' => null,
            'descricao' => 'Recebível de cartão',
            'valor_bruto' => $bruto,
            'taxa_percentual' => $taxa,
            'valor_liquido' => round($bruto * (1 - $taxa / 100), 2),
            'parcela_numero' => 1,
            'parcela_total' => 1,
            'data_venda' => now()->toDateString(),
            'data_prevista' => now()->addDays(30)->toDateString(),
            'cancelado_em' => null,
        ];
    }

    /** Previsto: data prevista no futuro. */
    public function previsto(): static
    {
        return $this->state(fn () => ['data_prevista' => now()->addDays(30)->toDateString()]);
    }

    /** Recebido: data prevista já alcançada. */
    public function recebido(): static
    {
        return $this->state(fn () => ['data_prevista' => now()->subDay()->toDateString()]);
    }

    /** Cancelado (venda estornada). */
    public function cancelado(): static
    {
        return $this->state(fn () => ['cancelado_em' => now()]);
    }
}
