<?php

declare(strict_types=1);

namespace App\Modules\Conta\Services;

use App\Enums\TipoConta;
use App\Modules\Conta\DTOs\ContaData;
use App\Modules\Conta\Models\Conta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContaService
{
    /**
     * @return Collection<int, Conta>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = Conta::orderByDesc('eh_caixa_padrao')->orderBy('nome');

        if (! empty($filtros['q'])) {
            $query->where('nome', 'like', '%'.$filtros['q'].'%');
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $query->where('ativo', (bool) $filtros['ativo']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        return $query->get();
    }

    public function criar(ContaData $dados, int $empresaId): Conta
    {
        return DB::transaction(function () use ($dados, $empresaId) {
            $attrs = $this->normalizarPorTipo($dados);
            $attrs['empresa_id'] = $empresaId;

            $conta = Conta::create($attrs);
            $this->garantirPadraoUnico($conta);

            return $conta;
        });
    }

    public function atualizar(Conta $conta, ContaData $dados): Conta
    {
        return DB::transaction(function () use ($conta, $dados) {
            $conta->update($this->normalizarPorTipo($dados));
            $this->garantirPadraoUnico($conta);

            return $conta->fresh();
        });
    }

    public function excluir(Conta $conta): void
    {
        // Soft delete: lancamentos historicos apontam para o id; nunca force delete.
        $conta->delete();
    }

    /**
     * Cria as contas financeiras padrao de uma empresa recem-criada:
     * a conta Caixa (dinheiro fisico, gaveta) e uma Conta Bancaria (destino
     * padrao dos recebiveis de cartao). rede_id/empresa_id explicitos — o
     * EmpresaTrait respeita quando ja setados.
     */
    public function semearPadrao(int $redeId, int $empresaId): void
    {
        Conta::create([
            'rede_id' => $redeId,
            'empresa_id' => $empresaId,
            'nome' => 'Caixa',
            'tipo' => TipoConta::Caixa,
            'saldo_inicial' => 0,
            'ativo' => true,
            'eh_caixa_padrao' => true,
        ]);

        Conta::create([
            'rede_id' => $redeId,
            'empresa_id' => $empresaId,
            'nome' => 'Conta Bancária',
            'tipo' => TipoConta::Banco,
            'saldo_inicial' => 0,
            'ativo' => true,
            'eh_destino_recebivel_padrao' => true,
        ]);
    }

    /**
     * Coerencia por tipo: a gaveta (caixa) nao guarda dados de banco nem e destino
     * de recebivel; banco/carteira nao e a conta-caixa da gaveta.
     *
     * @return array<string, mixed>
     */
    private function normalizarPorTipo(ContaData $dados): array
    {
        $attrs = $dados->toArray();

        if ($dados->tipo === TipoConta::Caixa) {
            $attrs['eh_destino_recebivel_padrao'] = false;
            $attrs['instituicao'] = null;
            $attrs['agencia'] = null;
            $attrs['numero'] = null;
        } else {
            $attrs['eh_caixa_padrao'] = false;
        }

        return $attrs;
    }

    /**
     * Garante no maximo uma conta-caixa padrao e um destino-de-recebivel padrao
     * por empresa (desmarca as demais). rede scope permanece via BaseModel.
     */
    private function garantirPadraoUnico(Conta $conta): void
    {
        if ($conta->eh_caixa_padrao) {
            Conta::withoutGlobalScope('empresa')
                ->where('empresa_id', $conta->empresa_id)
                ->where('id', '!=', $conta->id)
                ->where('eh_caixa_padrao', true)
                ->update(['eh_caixa_padrao' => false]);
        }

        if ($conta->eh_destino_recebivel_padrao) {
            Conta::withoutGlobalScope('empresa')
                ->where('empresa_id', $conta->empresa_id)
                ->where('id', '!=', $conta->id)
                ->where('eh_destino_recebivel_padrao', true)
                ->update(['eh_destino_recebivel_padrao' => false]);
        }
    }
}
