<?php

declare(strict_types=1);

use App\Modules\Tenant\Models\Plano;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Franquia diaria de analises por IA, por licenca.
 *
 * **Analises, nao tokens.** Token e a unidade que custa, mas nao e a unidade que o lojista
 * entende: "38.400 de 50.000 tokens" nao diz nada a quem toca um salao, enquanto "8 de 10
 * analises hoje" diz tudo. E o payload daqui e de tamanho previsivel (um resumo de no maximo
 * 6 segmentos), entao contar analises e um bom procurador do custo. O consumo real em tokens
 * continua gravado em `analises_ia` para medicao — so nao e ele quem barra.
 *
 * Uma coluna so, sem `tem_ia` ao lado: `0` ja significa "sem IA" de forma inequivoca, e
 * `Plano::temIa()` deriva a flag. Preserva a convencao do projeto de que todo limite e finito.
 *
 * Alteracao de `planos` mora em database/migrations/ e nao no modulo Ia, seguindo o
 * precedente de `remove_tem_relatorios_from_planos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->unsignedInteger('limite_analises_ia_dia')->default(0)->after('tem_financeiro');
        });

        // Retroativo: o Pro ja nasce com a franquia; o Gratis fica em 0 (default).
        Plano::query()
            ->where('slug', Plano::PRO)
            ->update(['limite_analises_ia_dia' => 10]);
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('limite_analises_ia_dia');
        });
    }
};
