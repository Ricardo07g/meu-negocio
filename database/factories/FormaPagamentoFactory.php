<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoFormaPagamento;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormaPagamento>
 */
class FormaPagamentoFactory extends Factory
{
    protected $model = FormaPagamento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'nome' => 'Dinheiro',
            'tipo' => TipoFormaPagamento::Dinheiro,
            'ativo' => true,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
            'permite_parcelas' => false,
            'max_parcelas' => null,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function pix(): static
    {
        return $this->state(fn () => ['nome' => 'Pix', 'tipo' => TipoFormaPagamento::Pix]);
    }

    /** PIX via maquineta/adquirente: gera recebível (D+N, com taxa), não cai na gaveta. */
    public function pixMaquineta(): static
    {
        return $this->state(fn () => [
            'nome' => 'Pix Maquineta',
            'tipo' => TipoFormaPagamento::Pix,
            'gera_recebivel' => true,
            'dias_liquidacao' => 1,
            'taxa_percentual' => 0.99,
        ]);
    }

    public function debito(): static
    {
        return $this->state(fn () => [
            'nome' => 'Cartão de Débito',
            'tipo' => TipoFormaPagamento::CartaoDebito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 1,
            'taxa_percentual' => 1.99,
        ]);
    }

    public function credito(): static
    {
        return $this->state(fn () => [
            'nome' => 'Cartão de Crédito',
            'tipo' => TipoFormaPagamento::CartaoCredito,
            'gera_recebivel' => true,
            'dias_liquidacao' => 30,
            'taxa_percentual' => 3.20,
            'permite_parcelas' => true,
            'max_parcelas' => 12,
        ]);
    }

    public function crediario(): static
    {
        return $this->state(fn () => [
            'nome' => 'Crediário',
            'tipo' => TipoFormaPagamento::Crediario,
            'gera_recebivel' => false,
            'dias_liquidacao' => 0,
            'taxa_percentual' => 0,
            'permite_parcelas' => false,
            'max_parcelas' => 12,
        ]);
    }
}
