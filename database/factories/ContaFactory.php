<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoConta;
use App\Modules\Conta\Models\Conta;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conta>
 */
class ContaFactory extends Factory
{
    protected $model = Conta::class;

    public function definition(): array
    {
        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'nome' => 'Caixa',
            'tipo' => TipoConta::Caixa,
            'saldo_inicial' => 0,
            'ativo' => true,
            'eh_caixa_padrao' => true,
            'eh_destino_recebivel_padrao' => false,
        ];
    }

    public function caixa(): static
    {
        return $this->state(fn () => [
            'nome' => 'Caixa',
            'tipo' => TipoConta::Caixa,
            'eh_caixa_padrao' => true,
            'eh_destino_recebivel_padrao' => false,
        ]);
    }

    public function banco(): static
    {
        return $this->state(fn () => [
            'nome' => 'Conta Bancária',
            'tipo' => TipoConta::Banco,
            'eh_caixa_padrao' => false,
            'eh_destino_recebivel_padrao' => true,
        ]);
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
