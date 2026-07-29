<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial e estado da licenca, nao um terceiro plano: durante o teste a unidade ESTA no Pro
 * de verdade, entao nada muda em feature flags, middleware ou gates de menu.
 *
 * Nulo = sem trial (unidade contratada ou trial ja encerrado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->date('trial_expira_em')->nullable()->after('plano_id');
            $table->index('trial_expira_em');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['trial_expira_em']);
            $table->dropColumn('trial_expira_em');
        });
    }
};
