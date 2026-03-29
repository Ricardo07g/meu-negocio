<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('contas', 'redes');

        $tabelas = [
            'empresas',
            'usuarios',
            'clientes',
            'servicos',
            'agendamentos',
            'pagamentos',
            'despesas',
            'produtos',
            'movimentos_estoque',
            'vendas_pacote',
        ];

        foreach ($tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->renameColumn('conta_id', 'rede_id');
            });
        }
    }

    public function down(): void
    {
        $tabelas = [
            'empresas',
            'usuarios',
            'clientes',
            'servicos',
            'agendamentos',
            'pagamentos',
            'despesas',
            'produtos',
            'movimentos_estoque',
            'vendas_pacote',
        ];

        foreach ($tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->renameColumn('rede_id', 'conta_id');
            });
        }

        Schema::rename('redes', 'contas');
    }
};
