<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAgendamento;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agendamento>
 */
class AgendamentoFactory extends Factory
{
    protected $model = Agendamento::class;

    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('now', '+1 month');
        $fim = (clone $inicio)->modify('+1 hour');

        return [
            'empresa_id' => EmpresaFactory::new(),
            'rede_id' => fn (array $attrs) => Empresa::find($attrs['empresa_id'])->rede_id,
            'cliente_id' => fn (array $attrs) => ClienteFactory::new()->state([
                'rede_id' => $attrs['rede_id'],
            ]),
            'servico_id' => fn (array $attrs) => ServicoFactory::new()->state([
                'rede_id' => $attrs['rede_id'],
            ]),
            'atendente_id' => fn (array $attrs) => UsuarioFactory::new()->atendente()->state([
                'rede_id' => $attrs['rede_id'],
                'empresa_id' => $attrs['empresa_id'],
            ]),
            'venda_etapas_id' => null,
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => StatusAgendamento::Agendado,
            'observacoes' => null,
        ];
    }

    public function confirmado(): static
    {
        return $this->state(fn () => ['status' => StatusAgendamento::Confirmado]);
    }

    public function finalizado(): static
    {
        return $this->state(fn () => ['status' => StatusAgendamento::Finalizado]);
    }

    public function cancelado(): static
    {
        return $this->state(fn () => ['status' => StatusAgendamento::Cancelado]);
    }
}
