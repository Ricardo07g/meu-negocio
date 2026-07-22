<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

class PermissaoSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Catalogo completo de permissoes do sistema
        $permissoes = [
            // Rede
            'rede.ver', 'rede.editar', 'rede.configurar', 'rede.cobranca',
            // Empresa
            'empresa.ver', 'empresa.criar', 'empresa.editar', 'empresa.excluir',
            // Usuario
            'usuario.ver', 'usuario.criar', 'usuario.editar', 'usuario.excluir',
            // Cliente
            'cliente.ver', 'cliente.criar', 'cliente.editar', 'cliente.excluir',
            // Servico
            'servico.ver', 'servico.criar', 'servico.editar', 'servico.excluir',
            // Agendamento
            'agendamento.ver', 'agendamento.criar', 'agendamento.editar', 'agendamento.cancelar', 'agendamento.excluir',
            // Financeiro
            'financeiro.ver', 'financeiro.criar', 'financeiro.editar', 'financeiro.excluir', 'financeiro.relatorio',
            // Pagamento
            'pagamento.ver', 'pagamento.criar', 'pagamento.editar', 'pagamento.excluir',
            // Despesa
            'despesa.ver', 'despesa.criar', 'despesa.editar', 'despesa.excluir',
            // Categoria de Despesa
            'categoria_despesa.ver', 'categoria_despesa.criar', 'categoria_despesa.editar', 'categoria_despesa.excluir',
            // Forma de Pagamento
            'forma_pagamento.ver', 'forma_pagamento.criar', 'forma_pagamento.editar', 'forma_pagamento.excluir',
            // Conta financeira
            'conta.ver', 'conta.criar', 'conta.editar', 'conta.excluir',
            // Estoque
            'estoque.ver', 'estoque.criar', 'estoque.editar', 'estoque.excluir',
            // Produto
            'produto.ver', 'produto.criar', 'produto.editar', 'produto.excluir',
            // Movimento estoque
            'movimento_estoque.ver', 'movimento_estoque.criar',
            // Perfil de Acesso (slug "papel" mantido por compatibilidade com codigo legado)
            'papel.ver', 'papel.criar', 'papel.editar', 'papel.excluir',
            // Plano
            'plano.ver', 'plano.alterar',
        ];

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        }

        // Apenas o perfil Admin master e seedado, com todas as permissoes.
        // Demais perfis sao criados pelo Admin via /perfis-acesso na UI.
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissoes);

        // Limpar cache para que mudancas em Permissions/Roles sejam refletidas
        // imediatamente em chamadas subsequentes (evita o tipico "cache stale"
        // do Spatie ao seedar e usar can() na mesma request).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
