<?php

namespace Database\Factories;

use App\Enums\CondicaoPagamento;
use App\Enums\StatusPagamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagamento>
 */
class PagamentoFactory extends Factory
{
    protected $model = Pagamento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'cliente_id' => null,
            'agendamento_id' => null,
            'venda_etapas_id' => null,
            'venda_produto_id' => null,
            'valor_total' => fake()->randomFloat(2, 50, 2000),
            'desconto' => 0,
            'acrescimo' => 0,
            'condicao_pagamento' => CondicaoPagamento::AVista,
            'forma_recebimento_prazo' => null,
            'mes_referencia' => now()->startOfMonth()->format('Y-m-d'),
            'status' => StatusPagamento::Pendente,
            'descricao' => fake('pt_BR')->optional()->sentence(),
        ];
    }

    public function pago(): static
    {
        return $this->state(fn () => ['status' => StatusPagamento::Pago]);
    }

    public function aPrazo(): static
    {
        return $this->state(fn () => ['condicao_pagamento' => CondicaoPagamento::APrazo]);
    }
}
