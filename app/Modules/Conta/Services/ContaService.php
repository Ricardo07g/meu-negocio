<?php

declare(strict_types=1);

namespace App\Modules\Conta\Services;

use App\Enums\TipoConta;
use App\Modules\Conta\Models\Conta;

class ContaService
{
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
