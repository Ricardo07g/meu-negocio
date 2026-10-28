<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Verificacao de e-mail (ADR-0022): usuario com a coluna nula fica preso na tela
 * "confirme seu e-mail" ate clicar no link.
 *
 * Quem ja existe nasce verificado: a regra vale para cadastros novos, e trancar a
 * base atual fora do sistema no deploy seria um apagao, nao uma feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::table('usuarios')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
