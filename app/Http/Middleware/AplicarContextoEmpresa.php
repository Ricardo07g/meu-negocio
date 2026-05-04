<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ME-010 v3: aplica o "contexto de empresa" da URL na sessao.
 *
 * Quando o usuario filtra uma listagem por uma unica empresa via
 * `?empresa_id=X`, persiste a escolha em `session('empresa_contexto_atual')`.
 * Esse contexto vira a empresa-base para todas as operacoes da request:
 *  - `EmpresaTrait` filtra global scope por essa empresa.
 *  - Forms/actions criadas a partir da listagem herdam empresa_id automaticamente.
 *
 * Regras (decisao do usuario — URL > session):
 *  - URL `?empresa_id=X` (X numerico em `empresas_atuais`): seta session.
 *  - URL `?empresa_id=todas`: limpa session (volta para visao completa do header).
 *  - URL sem param: respeita session existente (se ainda valida); se invalida (id
 *    nao esta mais em `empresas_atuais`), limpa.
 *
 * Aplicar APOS `verificar.empresa`, pois depende de `empresas_atuais`.
 */
class AplicarContextoEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->query('empresa_id');
        $empresasAtuais = array_map('intval', (array) session('empresas_atuais', []));

        if (is_string($param) && in_array(strtolower($param), ['todas', 'all', ''], true)) {
            session()->forget('empresa_contexto_atual');
        } elseif (is_numeric($param)) {
            $id = (int) $param;
            if (in_array($id, $empresasAtuais, true)) {
                session(['empresa_contexto_atual' => $id]);
            }
            // Id invalido (fora do escopo do usuario): ignora silenciosamente.
        } else {
            // Sem param: poda contexto stale.
            $contexto = session('empresa_contexto_atual');
            if (! is_int($contexto) || ! in_array($contexto, $empresasAtuais, true)) {
                session()->forget('empresa_contexto_atual');
            }
        }

        return $next($request);
    }
}
