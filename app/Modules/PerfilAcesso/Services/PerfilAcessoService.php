<?php

namespace App\Modules\PerfilAcesso\Services;

use App\Exceptions\NegocioException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PerfilAcessoService
{
    /** Icone Feather por modulo (prefixo antes do "."). */
    private const ICONES = [
        'rede' => 'feather-globe',
        'empresa' => 'feather-home',
        'usuario' => 'feather-users',
        'cliente' => 'feather-user',
        'servico' => 'feather-briefcase',
        'agendamento' => 'feather-calendar',
        'financeiro' => 'feather-dollar-sign',
        'pagamento' => 'feather-credit-card',
        'despesa' => 'feather-trending-down',
        'categoria_despesa' => 'feather-tag',
        'estoque' => 'feather-archive',
        'produto' => 'feather-package',
        'movimento_estoque' => 'feather-shuffle',
        'papel' => 'feather-shield',
        'plano' => 'feather-award',
    ];

    /** Rotulo amigavel exibido no header de cada card. */
    private const ROTULOS_MODULO = [
        'rede' => 'Rede',
        'empresa' => 'Empresa',
        'usuario' => 'Usuário',
        'cliente' => 'Cliente',
        'servico' => 'Serviço',
        'agendamento' => 'Agendamento',
        'financeiro' => 'Financeiro',
        'pagamento' => 'Pagamento',
        'despesa' => 'Despesa',
        'categoria_despesa' => 'Categoria de Despesa',
        'estoque' => 'Estoque',
        'produto' => 'Produto',
        'movimento_estoque' => 'Movimento de Estoque',
        'papel' => 'Perfil de Acesso',
        'plano' => 'Plano',
    ];

    /** Rotulo amigavel da acao (parte apos o "."). */
    private const ROTULOS_ACAO = [
        'ver' => 'Visualizar',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
        'cancelar' => 'Cancelar',
        'relatorio' => 'Relatório',
        'configurar' => 'Configurar',
        'cobranca' => 'Cobrança',
        'alterar' => 'Alterar',
    ];

    public function listar(): EloquentCollection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Retorna as permissoes agrupadas pelo prefixo antes do primeiro ponto
     * (ex.: "cliente.criar" -> grupo "cliente"), enriquecidas com metadados
     * para a UI (icone Feather, rotulo amigavel do modulo, rotulo da acao).
     *
     * Estrutura de cada item: ['modulo', 'icone', 'label', 'permissoes' => [['id', 'name', 'rotulo'], ...]].
     */
    public function permissoesAgrupadas(): Collection
    {
        return Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $p) => explode('.', $p->name)[0])
            ->map(function ($perms, $modulo) {
                return [
                    'modulo' => $modulo,
                    'icone' => self::ICONES[$modulo] ?? 'feather-circle',
                    'label' => self::ROTULOS_MODULO[$modulo] ?? ucfirst(str_replace('_', ' ', $modulo)),
                    'permissoes' => $perms->map(function (Permission $p) {
                        $acao = explode('.', $p->name)[1] ?? $p->name;

                        return [
                            'id' => $p->id,
                            'name' => $p->name,
                            'rotulo' => self::ROTULOS_ACAO[$acao] ?? ucfirst(str_replace('_', ' ', $acao)),
                        ];
                    })->values(),
                ];
            });
    }

    public function criar(string $nome, array $permissoes = []): Role
    {
        $perfil = Role::create(['name' => $nome, 'guard_name' => 'web']);
        $perfil->syncPermissions($permissoes);

        return $perfil;
    }

    public function atualizar(Role $perfil, string $nome, array $permissoes = []): Role
    {
        if ($perfil->name === 'Admin') {
            throw new NegocioException('O perfil Admin não pode ser editado.');
        }

        $perfil->update(['name' => $nome]);
        $perfil->syncPermissions($permissoes);

        return $perfil->fresh();
    }

    public function excluir(Role $perfil): void
    {
        if ($perfil->name === 'Admin') {
            throw new NegocioException('O perfil Admin não pode ser excluído.');
        }

        if ($perfil->users()->count() > 0) {
            throw new NegocioException('Este perfil possui usuários vinculados. Remova-os antes de excluir.');
        }

        $perfil->delete();
    }
}
