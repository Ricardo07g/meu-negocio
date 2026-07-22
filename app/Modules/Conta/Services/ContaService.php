<?php

declare(strict_types=1);

namespace App\Modules\Conta\Services;

use App\Enums\TipoConta;
use App\Exceptions\NegocioException;
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
        $query = Conta::with('empresa:id,nome')->orderByDesc('eh_caixa_padrao')->orderBy('nome');

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
        // A gaveta (Caixa) e do sistema (semearPadrao); o lojista so cria banco/carteira.
        if ($dados->tipo === TipoConta::Caixa) {
            throw new NegocioException('A conta Caixa é do sistema e já existe na empresa.');
        }

        return DB::transaction(fn () => Conta::create([
            'empresa_id' => $empresaId,
            'nome' => $dados->nome,
            'tipo' => $dados->tipo,
            'saldo_inicial' => $dados->saldo_inicial,
            'ativo' => $dados->ativo,
            'eh_caixa_padrao' => false,
            'eh_destino_recebivel_padrao' => false,
            'instituicao' => $dados->instituicao,
            'agencia' => $dados->agencia,
            'numero' => $dados->numero,
        ]));
    }

    /** Renomeia uma conta — unico campo editavel da conta Caixa do sistema. */
    public function renomear(Conta $conta, string $nome): Conta
    {
        $conta->update(['nome' => $nome]);

        return $conta->fresh();
    }

    public function atualizar(Conta $conta, ContaData $dados): Conta
    {
        // Caixa do sistema: so o nome muda (defesa — o controller ja roteia para renomear).
        if ($conta->ehProtegida()) {
            return $this->renomear($conta, $dados->nome);
        }

        if ($dados->tipo === TipoConta::Caixa) {
            throw new NegocioException('Uma conta comum não pode virar Caixa.');
        }

        return DB::transaction(function () use ($conta, $dados) {
            // Flags (caixa/destino-recebivel padrao) sao internas: geridas pelo seed, nunca pelo form.
            $conta->update([
                'nome' => $dados->nome,
                'tipo' => $dados->tipo,
                'saldo_inicial' => $dados->saldo_inicial,
                'ativo' => $dados->ativo,
                'instituicao' => $dados->instituicao,
                'agencia' => $dados->agencia,
                'numero' => $dados->numero,
            ]);

            return $conta->fresh();
        });
    }

    /**
     * Exclui (soft) apenas a conta que nao e do sistema, sem movimentacoes e sem vinculo —
     * caso contrario o lojista deve inativar (mesma trilha de PerfilAcessoService::excluir).
     */
    public function excluir(Conta $conta): void
    {
        if ($conta->ehProtegida()) {
            throw new NegocioException('A conta Caixa é do sistema e não pode ser excluída.');
        }

        if ($conta->temMovimentacoes()) {
            throw new NegocioException('Esta conta possui movimentações e não pode ser excluída. Inative-a em vez de excluir.');
        }

        if ($conta->estaEmUso()) {
            throw new NegocioException('Esta conta está vinculada a formas de pagamento ou caixas. Remova o vínculo antes de excluir.');
        }

        $conta->delete();
    }

    public function inativar(Conta $conta): void
    {
        if ($conta->ehProtegida()) {
            throw new NegocioException('A conta Caixa é do sistema e não pode ser inativada.');
        }

        if ($conta->temFormaAtivaVinculada()) {
            throw new NegocioException('Uma forma de pagamento ativa ainda usa esta conta; troque o destino da forma antes de inativar.');
        }

        $conta->update(['ativo' => false]);
    }

    public function reativar(Conta $conta): void
    {
        $conta->update(['ativo' => true]);
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
}
