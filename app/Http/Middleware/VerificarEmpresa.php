<?php

namespace App\Http\Middleware;

use App\Exceptions\EmpresaNaoEncontradaException;
use App\Modules\Tenant\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que o usuario autenticado tem ao menos uma empresa acessivel
 * e popula `session('empresas_atuais')` com as empresas correntes para
 * o EmpresaTrait usar como filtro multi-empresa (ME-007).
 *
 * Regras:
 *  - Admin: sessao default = todas as empresas da rede.
 *  - Nao-admin: sessao default = empresas presentes na pivot empresa_usuario.
 *  - Sessao ja preenchida: valida que cada ID continua acessivel ao usuario;
 *    IDs invalidos sao podados silenciosamente. Se sobrar selecao vazia,
 *    repopula com o default acima.
 *  - Nao-admin sem nenhuma empresa no pivot: 403 com mensagem orientando
 *    procurar o admin (caso degenerado, nao deveria ocorrer com a validacao
 *    do SalvarUsuarioRequest, mas defendemos aqui tambem).
 */
class VerificarEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        $empresasAcessiveis = $this->resolverEmpresasAcessiveis($usuario);

        if ($empresasAcessiveis === []) {
            // Nao-admin sem pivot ou Admin numa rede sem empresas — caso degenerado.
            throw new EmpresaNaoEncontradaException;
        }

        $selecionadas = $this->resolverSelecaoSessao($empresasAcessiveis);

        session(['empresas_atuais' => $selecionadas]);

        return $next($request);
    }

    /**
     * Retorna o conjunto completo de IDs de empresa que o usuario pode
     * acessar (independente da selecao atual).
     *
     * @return int[]
     */
    private function resolverEmpresasAcessiveis($usuario): array
    {
        if ($usuario->hasRole('Admin')) {
            // Admin ve toda a rede. RedeTrait ja restringe Empresa::query() a rede do usuario.
            return Empresa::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $usuario->empresas()->pluck('empresas.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Pega a selecao atual da sessao (se houver) e poda IDs nao acessiveis.
     * Se sobrar vazio, retorna o default (todas as acessiveis).
     *
     * @param  int[]  $empresasAcessiveis
     * @return int[]
     */
    private function resolverSelecaoSessao(array $empresasAcessiveis): array
    {
        $sessao = session('empresas_atuais');

        if (! is_array($sessao) || $sessao === []) {
            return $empresasAcessiveis;
        }

        $sessao = array_values(array_map('intval', $sessao));
        $valida = array_values(array_intersect($sessao, $empresasAcessiveis));

        return $valida === [] ? $empresasAcessiveis : $valida;
    }
}
