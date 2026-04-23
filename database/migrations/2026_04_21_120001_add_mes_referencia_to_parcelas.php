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

        DB::statement('
            UPDATE parcelas_pagamento pp
            INNER JOIN pagamentos p ON pp.pagamento_id = p.id
            SET pp.mes_referencia = p.mes_referencia
            WHERE pp.mes_referencia IS NULL
        ');

        DB::statement('
            UPDATE parcelas_despesa pd
            INNER JOIN despesas d ON pd.despesa_id = d.id
            SET pd.mes_referencia = d.mes_referencia
            WHERE pd.mes_referencia IS NULL
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
