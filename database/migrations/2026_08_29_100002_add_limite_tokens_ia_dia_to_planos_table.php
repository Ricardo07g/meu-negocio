<?php

declare(strict_types=1);

use App\Modules\Tenant\Models\Plano;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cota diaria de tokens de IA por licenca.
 *
 * Uma coluna so, sem `tem_ia` ao lado: `0` ja significa "sem IA" de forma inequivoca, e
 * `Plano::temIa()` deriva a flag. Isso preserva a convencao do projeto de que **todo limite
 * e finito** (nao existe `0 = ilimitado` aqui).
 *
 * Alteracao de `planos` mora em database/migrations/ e nao no modulo Ia, seguindo o
 * precedente de `remove_tem_relatorios_from_planos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->unsignedInteger('limite_tokens_ia_dia')->default(0)->after('tem_financeiro');
        });

        // Retroativo: o Pro ja nasce com a cota; o Gratis fica em 0 (default).
        Plano::query()
            ->where('slug', Plano::PRO)
            ->update(['limite_tokens_ia_dia' => 50000]);
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('limite_tokens_ia_dia');
        });
    }
};
