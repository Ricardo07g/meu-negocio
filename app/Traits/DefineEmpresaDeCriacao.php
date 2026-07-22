<?php

declare(strict_types=1);

namespace App\Traits;

use Closure;

/**
 * Define a empresa de criacao (ME-010) ao redor de uma operacao de escrita.
 *
 * Centraliza o contrato da chave de sessao `empresa_criacao_atual`, usada pelo
 * `EmpresaTrait::creating` para garantir que toda a cascata de um registro
 * transacional (ex.: Venda -> Pagamento -> Parcela -> Baixa -> Lancamento)
 * herde a mesma empresa, mesmo quando ha varias selecionadas no header ou o
 * usuario chegou via link direto sem passar pela listagem.
 *
 * O `finally` garante que a chave seja sempre limpa apos a operacao, evitando
 * que o override vaze para requests seguintes.
 */
trait DefineEmpresaDeCriacao
{
    /**
     * Executa o callback com a empresa de criacao fixada na sessao.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function comEmpresaDeCriacao(?int $empresaId, Closure $callback): mixed
    {
        if ($empresaId !== null) {
            session(['empresa_criacao_atual' => $empresaId]);
        }

        try {
            return $callback();
        } finally {
            session()->forget('empresa_criacao_atual');
        }
    }
}
