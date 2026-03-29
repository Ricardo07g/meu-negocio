<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissaoSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar todas as permissoes
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
            // Profissional
            'profissional.ver', 'profissional.criar', 'profissional.editar', 'profissional.excluir',
            // Agendamento
            'agendamento.ver', 'agendamento.criar', 'agendamento.editar', 'agendamento.cancelar', 'agendamento.excluir',
            // Financeiro
            'financeiro.ver', 'financeiro.criar', 'financeiro.editar', 'financeiro.excluir', 'financeiro.relatorio',
            // Pagamento
            'pagamento.ver', 'pagamento.criar', 'pagamento.editar', 'pagamento.excluir',
            // Despesa
            'despesa.ver', 'despesa.criar', 'despesa.editar', 'despesa.excluir',
            // Estoque
            'estoque.ver', 'estoque.criar', 'estoque.editar', 'estoque.excluir',
            // Produto
            'produto.ver', 'produto.criar', 'produto.editar', 'produto.excluir',
            // Movimento estoque
            'movimento_estoque.ver', 'movimento_estoque.criar',
            // Papel
            'papel.ver', 'papel.criar', 'papel.editar', 'papel.excluir',
            // Plano
            'plano.ver', 'plano.alterar',
        ];

        foreach ($permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        }

        // Criar papeis e atribuir permissoes

        // Admin - todas as permissoes
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissoes);

        // Gerente
        $gerente = Role::firstOrCreate(['name' => 'Gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions([
            'cliente.ver', 'cliente.criar', 'cliente.editar', 'cliente.excluir',
            'servico.ver', 'servico.criar', 'servico.editar', 'servico.excluir',
            'profissional.ver',
            'agendamento.ver', 'agendamento.criar', 'agendamento.editar', 'agendamento.cancelar', 'agendamento.excluir',
            'pagamento.ver', 'pagamento.criar', 'pagamento.editar', 'pagamento.excluir',
            'despesa.ver', 'despesa.criar',
            'financeiro.ver',
            'empresa.ver',
            'usuario.ver',
        ]);

        // Profissional
        $profissional = Role::firstOrCreate(['name' => 'Profissional', 'guard_name' => 'web']);
        $profissional->syncPermissions([
            'agendamento.ver', 'agendamento.criar',
            'cliente.ver',
            'servico.ver',
        ]);

        // Recepcao
        $recepcao = Role::firstOrCreate(['name' => 'Recepcao', 'guard_name' => 'web']);
        $recepcao->syncPermissions([
            'cliente.ver', 'cliente.criar', 'cliente.editar', 'cliente.excluir',
            'agendamento.ver', 'agendamento.criar', 'agendamento.editar', 'agendamento.cancelar', 'agendamento.excluir',
            'pagamento.ver', 'pagamento.criar',
            'servico.ver',
            'profissional.ver',
        ]);

        // Financeiro
        $financeiro = Role::firstOrCreate(['name' => 'Financeiro', 'guard_name' => 'web']);
        $financeiro->syncPermissions([
            'financeiro.ver', 'financeiro.criar', 'financeiro.editar', 'financeiro.excluir', 'financeiro.relatorio',
            'pagamento.ver', 'pagamento.criar', 'pagamento.editar', 'pagamento.excluir',
            'despesa.ver', 'despesa.criar', 'despesa.editar', 'despesa.excluir',
        ]);

        // Estoque
        $estoque = Role::firstOrCreate(['name' => 'Estoque', 'guard_name' => 'web']);
        $estoque->syncPermissions([
            'produto.ver', 'produto.criar', 'produto.editar', 'produto.excluir',
            'movimento_estoque.ver', 'movimento_estoque.criar',
            'estoque.ver', 'estoque.criar', 'estoque.editar', 'estoque.excluir',
        ]);

        // Visualizador - somente permissoes .ver
        $visualizador = Role::firstOrCreate(['name' => 'Visualizador', 'guard_name' => 'web']);
        $visualizador->syncPermissions(array_filter($permissoes, fn($p) => str_ends_with($p, '.ver')));
    }
}
