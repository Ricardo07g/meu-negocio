<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Policies;

use App\Modules\Usuario\Models\Usuario;

class FaturaPolicy
{
    /**
     * Assinatura e assunto do dono da conta: preco, fatura e teste gratuito so aparecem
     * para o Admin — no menu, no aviso de teste do layout e na propria tela.
     *
     * Esta Policy e a fonte unica dessa decisao; o isolamento por rede_id continua a
     * cargo do RedeTrait (global scope).
     */
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->hasRole('Admin');
    }

    /**
     * Trocar de plano e uma decisao de cobranca da rede: restrita ao Admin.
     */
    public function transicionar(Usuario $usuario): bool
    {
        return $usuario->hasRole('Admin');
    }

    /**
     * Reabrir o teste gratuito muda o que a rede paga no mes: mesma alcada da troca de plano.
     */
    public function renovarTrial(Usuario $usuario): bool
    {
        return $usuario->hasRole('Admin');
    }
}
