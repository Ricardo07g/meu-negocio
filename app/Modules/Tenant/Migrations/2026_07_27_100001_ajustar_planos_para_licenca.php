<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * O plano deixa de ser "assinatura da rede" e passa a ser a licenca de UMA empresa.
 *
 * - `slug` vira a chave tecnica estavel (o codigo buscava por `nome`, que e rotulo).
 * - `preco_mensal` -> `preco_por_licenca`: a semantica mudou, o nome antigo enganaria.
 * - `max_empresas` sai: um plano nao concede N empresas, ele e comprado PARA uma empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('planos', 'slug')) {
            Schema::table('planos', function (Blueprint $table) {
                $table->string('slug', 20)->nullable()->after('id');
            });

            // Bases existentes: o slug nasce do nome atual (free/basic/pro/business).
            // A consolidacao em gratis/pro acontece na migration seguinte.
            DB::table('planos')->whereNull('slug')->update(['slug' => DB::raw('nome')]);

            Schema::table('planos', function (Blueprint $table) {
                $table->unique('slug');
            });
        }

        if (Schema::hasColumn('planos', 'preco_mensal')) {
            Schema::table('planos', function (Blueprint $table) {
                $table->renameColumn('preco_mensal', 'preco_por_licenca');
            });
        }

        if (Schema::hasColumn('planos', 'max_empresas')) {
            Schema::table('planos', function (Blueprint $table) {
                $table->dropColumn('max_empresas');
            });
        }
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->integer('max_empresas')->default(1);
        });

        Schema::table('planos', function (Blueprint $table) {
            $table->renameColumn('preco_por_licenca', 'preco_mensal');
        });

        Schema::table('planos', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
