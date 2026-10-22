<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Politica da rede (ADR-0022): ligado pelo Admin, todo usuario da rede precisa
 * configurar o 2FA antes de usar o sistema. Desligado = 2FA opcional por usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redes', function (Blueprint $table) {
            $table->boolean('exigir_2fa')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('redes', function (Blueprint $table) {
            $table->dropColumn('exigir_2fa');
        });
    }
};
