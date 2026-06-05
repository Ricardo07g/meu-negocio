<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Tenant\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait de isolamento por empresa (multi-tenant nivel 2).
 *
 * Aplica um global scope que filtra registros pelas empresas em contexto
 * atual. A resolucao do contexto segue uma cadeia de prioridade:
 *
 *  1. `session('empresa_contexto_atual')` (int): contexto explicito definido
 *     pelo filtro de listagem (URL `?empresa_id=X`). Quando setado, filtra
 *     SOMENTE essa empresa — vence o multi-select do header (ME-010 v3).
 *  2. `session('empresas_atuais')` (int[]): selecao multi do header
 *     (ME-007 / ME-009), gerenciada pelo middleware VerificarEmpresa.
 *  3. Fallback: `$usuario->empresa_id` (compat pre-ME-006).
 *
 * Comportamento:
 *  - Admin: idem; quando ha contexto setado, filtra por ele; sem sessao
 *    explicita, nao filtra (ve toda a rede via RedeTrait).
 *  - Nao-admin: filtra sempre.
 *  - Sem usuario autenticado (jobs, console, testes sem auth): nao filtra.
 *
 * Boot creating (cadeia de resolucao para preencher empresa_id):
 *  1. Modelo ja tem empresa_id setado: respeita.
 *  2. Contexto explicito: usa.
 *  3. Exatamente 1 empresa em `empresas_atuais`: usa.
 *  4. Override `empresa_criacao_atual` (int em `empresas_atuais`): usa
 *     — defesa em profundidade para baixas de parcela quando o usuario
 *     nao passou pela listagem (acesso via link direto).
 *  5. Senao: deixa null (caller deve passar empresa_id explicitamente).
 */
trait EmpresaTrait
{
    public static function bootEmpresaTrait(): void
    {
        static::addGlobalScope('empresa', function (Builder $query) {
            $usuario = static::resolverUsuarioSeguro();

            if (! $usuario) {
                return;
            }

            // Admin sem sessao explicita: nao filtra (ve tudo na rede).
            if ($usuario->hasRole('Admin') && ! session()->has('empresas_atuais')) {
                return;
            }

            $empresasIds = static::resolverEmpresasAtuais($usuario);

            if ($empresasIds === null) {
                return;
            }

            $coluna = $query->getModel()->getTable().'.empresa_id';

            if ($empresasIds === []) {
                // Selecao vazia (caso degenerado): nao retorna nada.
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn($coluna, $empresasIds);
        });

        static::creating(function ($model) {
            if ($model->empresa_id) {
                return;
            }

            $usuario = static::resolverUsuarioSeguro();

            if (! $usuario) {
                return;
            }

            $empresasIds = static::resolverEmpresasAtuais($usuario);

            // Sem contexto resolvivel: fallback para empresa default do usuario.
            if ($empresasIds === null || $empresasIds === []) {
                $model->empresa_id = $usuario->empresa_id;

                return;
            }

            // Exatamente 1 empresa selecionada: atribui automaticamente.
            if (count($empresasIds) === 1) {
                $model->empresa_id = $empresasIds[0];

                return;
            }

            // Mais de 1 empresa selecionada: aplica override por request, se
            // disponivel. ME-010: telas de operacao (Venda, Agenda, Despesa)
            // setam session('empresa_criacao_atual') com a empresa escolhida
            // no sub-seletor — o trait usa esse valor como contexto de
            // criacao para que entidades em cascata (Venda + Pagamento +
            // Parcela + Baixa + MovimentoCaixa) compartilhem a mesma empresa
            // sem precisar propagar empresa_id em cada Action.
            $override = session('empresa_criacao_atual');
            if (is_int($override) && in_array($override, $empresasIds, true)) {
                $model->empresa_id = $override;
            }
            // Sem override: deixa null (caller deve passar empresa_id explicitamente).
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Resolve o conjunto de empresas atuais para o usuario.
     *
     * Retorno:
     *  - null  → nao aplicar filtro (Admin sem sessao, ou sem fallback util).
     *  - []    → selecao vazia explicita (nao deveria acontecer com middleware ativo).
     *  - int[] → IDs de empresas a filtrar.
     */
    protected static function resolverEmpresasAtuais(mixed $usuario): ?array
    {
        // Prioridade 1: contexto explicito da listagem (ME-010 v3).
        $contexto = session('empresa_contexto_atual');
        if (is_int($contexto) && $contexto > 0) {
            return [$contexto];
        }

        // Prioridade 2: selecao multi do header.
        $sessao = session('empresas_atuais');
        if (is_array($sessao)) {
            return array_values(array_map('intval', $sessao));
        }

        // Sem sessao: fallback compat — empresa default do usuario.
        if ($usuario->empresa_id) {
            return [(int) $usuario->empresa_id];
        }

        // Admin sem sessao e sem empresa default: nao filtra.
        if ($usuario->hasRole('Admin')) {
            return null;
        }

        // Nao-admin sem empresa: nao deveria acessar nada.
        return [];
    }

    protected static function resolverUsuarioSeguro(): mixed
    {
        static $resolvendo = false;

        if ($resolvendo) {
            return null;
        }

        $resolvendo = true;

        try {
            $usuario = auth()->user();
        } finally {
            $resolvendo = false;
        }

        return $usuario;
    }
}
