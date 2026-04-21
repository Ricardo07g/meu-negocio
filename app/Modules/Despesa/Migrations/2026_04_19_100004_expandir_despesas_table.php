<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            $table->foreignId('categoria_despesa_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('categorias_despesa')
                ->nullOnDelete();

            $table->string('fornecedor_nome', 150)->nullable()->after('nome');
            $table->string('documento', 80)->nullable()->after('fornecedor_nome');
            $table->text('observacoes')->nullable()->after('documento');

            $table->decimal('valor_pago', 10, 2)->default(0)->after('valor');
            $table->string('forma_pagamento')->nullable()->after('valor_pago');

            $table->date('data_emissao')->nullable()->after('data');
            $table->date('data_vencimento')->nullable()->after('data_emissao');
            $table->date('competencia')->nullable()->after('data_vencimento');

            $table->string('status')->default('pendente')->after('competencia');

            $table->uuid('grupo_parcelamento_id')->nullable()->after('status');
            $table->unsignedSmallInteger('parcela_numero')->nullable()->after('grupo_parcelamento_id');
            $table->unsignedSmallInteger('parcela_total')->nullable()->after('parcela_numero');

            $table->index('data_vencimento');
            $table->index('competencia');
            $table->index('status');
            $table->index('grupo_parcelamento_id');
        });

        // Popular colunas novas com base no campo `data` legado
        DB::statement('UPDATE despesas SET data_emissao = data, data_vencimento = data, competencia = DATE_FORMAT(data, "%Y-%m-01") WHERE data IS NOT NULL');

        Schema::table('despesas', function (Blueprint $table) {
            $table->date('data_emissao')->nullable(false)->change();
            $table->date('data_vencimento')->nullable(false)->change();
            $table->date('competencia')->nullable(false)->change();

            $table->dropIndex(['data']);
            $table->dropColumn('data');
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            $table->date('data')->nullable()->after('valor');
        });

        DB::statement('UPDATE despesas SET data = data_emissao');

        Schema::table('despesas', function (Blueprint $table) {
            $table->date('data')->nullable(false)->change();
            $table->index('data');

            $table->dropIndex(['data_vencimento']);
            $table->dropIndex(['competencia']);
            $table->dropIndex(['status']);
            $table->dropIndex(['grupo_parcelamento_id']);

            $table->dropColumn([
                'categoria_despesa_id',
                'fornecedor_nome',
                'documento',
                'observacoes',
                'valor_pago',
                'forma_pagamento',
                'data_emissao',
                'data_vencimento',
                'competencia',
                'status',
                'grupo_parcelamento_id',
                'parcela_numero',
                'parcela_total',
            ]);
        });
    }
};
