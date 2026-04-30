<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna mes_referencia por parcela (permite competencia distinta
 * por parcela). Faz backfill copiando do titulo pai (pagamento ou despesa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcelas_pagamento', function (Blueprint $table) {
            $table->date('mes_referencia')->nullable()->after('data_vencimento');
            $table->index('mes_referencia');
        });

        Schema::table('parcelas_despesa', function (Blueprint $table) {
            $table->date('mes_referencia')->nullable()->after('data_vencimento');
            $table->index('mes_referencia');
        });

        // Backfill via subquery correlacionada — compativel com MySQL e
        // SQLite (testes rodam em SQLite in-memory). Equivalente ao UPDATE
        // ... INNER JOIN, mas sem syntax especifica de MySQL.
        DB::statement('
            UPDATE parcelas_pagamento
            SET mes_referencia = (
                SELECT pagamentos.mes_referencia
                FROM pagamentos
                WHERE pagamentos.id = parcelas_pagamento.pagamento_id
            )
            WHERE mes_referencia IS NULL
        ');

        DB::statement('
            UPDATE parcelas_despesa
            SET mes_referencia = (
                SELECT despesas.mes_referencia
                FROM despesas
                WHERE despesas.id = parcelas_despesa.despesa_id
            )
            WHERE mes_referencia IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('parcelas_pagamento', function (Blueprint $table) {
            $table->dropIndex(['mes_referencia']);
            $table->dropColumn('mes_referencia');
        });

        Schema::table('parcelas_despesa', function (Blueprint $table) {
            $table->dropIndex(['mes_referencia']);
            $table->dropColumn('mes_referencia');
        });
    }
};
