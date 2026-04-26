<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Atualiza o conjunto de empresas atualmente selecionadas pelo usuario
 * no seletor multi-empresa do header (ME-009). Persiste em
 * session('empresas_atuais'); o EmpresaTrait usa essa sessao como filtro
 * em todos os scopes.
 *
 * Valida que cada ID enviado seja efetivamente acessivel ao usuario
 * (Admin = rede inteira via RedeTrait; nao-admin = pivot empresa_usuario).
 * Selecao vazia e bloqueada com 422 — pelo menos 1 empresa deve estar
 * ativa para o sistema operar.
 */
class EmpresaAtualController extends Controller
{
    public function atualizar(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $usuario = $request->user();
        $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));

        $acessiveis = $usuario->hasRole('Admin')
            ? Empresa::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $usuario->empresas()->pluck('empresas.id')->map(fn ($id) => (int) $id)->all();

        $validos = array_values(array_intersect($ids, $acessiveis));

        if ($validos === []) {
            return response()->json([
                'mensagem' => 'Selecione ao menos uma empresa valida.',
            ], 422);
        }

        session(['empresas_atuais' => $validos]);

        return response()->json([
            'mensagem' => 'Empresas atualizadas.',
            'ids' => $validos,
        ]);
    }
}
