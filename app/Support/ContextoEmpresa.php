<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper para resolver a "empresa em contexto" do request atual.
 *
 * Cadeia de prioridade (espelha `EmpresaTrait::resolverEmpresasAtuais`):
 *   1. `session('empresa_contexto_atual')` — definido pelo filtro `?empresa_id=X`
 *      via middleware `aplicar.contexto.empresa`.
 *   2. `session('empresas_atuais')` quando contem exatamente 1 empresa.
 *   3. null — sem contexto resolvivel (multiplas empresas no header sem narrowing).
 *
 * Use em controllers/services que precisam saber em qual empresa o usuario
 * esta operando agora — por exemplo, listar atendentes via pivot.
 */
class ContextoEmpresa
{
    public static function resolver(): ?int
    {
        $contexto = session('empresa_contexto_atual');
        if (is_int($contexto) && $contexto > 0) {
            return $contexto;
        }

        $empresasAtuais = (array) session('empresas_atuais', []);
        if (count($empresasAtuais) === 1) {
            return (int) reset($empresasAtuais);
        }

        return null;
    }
}
