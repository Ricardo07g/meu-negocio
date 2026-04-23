<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('contas', 'redes');

        // pagamentos e despesas passaram a nascer já com rede_id (migrations posteriores);
        // aqui renomeamos apenas as herdadas que ainda usavam conta_id.
        $tabelas = [
            'empresas',
            'usuarios',
            'clientes',
            'servicos',
            'agendamentos',
            'produtos',
            'movimentos_estoque',
            'vendas_pacote',
        ];

        foreach ($tabelas as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'conta_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->renameColumn('conta_id', 'rede_id');
                });
            }
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
            'produtos',
            'movimentos_estoque',
            'vendas_pacote',
        ];

        foreach ($tabelas as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'rede_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->renameColumn('rede_id', 'conta_id');
                });
            }
        }

        Schema::rename('redes', 'contas');
    }
};
