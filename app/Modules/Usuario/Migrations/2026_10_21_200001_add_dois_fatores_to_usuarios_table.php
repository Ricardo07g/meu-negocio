<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA por app autenticador (TOTP — ADR-0022).
 *
 * - `two_factor_secret`: segredo TOTP, criptografado pelo cast `encrypted`. Preenchido
 *   ao iniciar a configuracao; so vale depois de `two_factor_confirmed_at`.
 * - `two_factor_recovery_codes`: hashes dos codigos de recuperacao (cast `encrypted:array`).
 * - `two_factor_ultimo_passo`: passo de 30s do ultimo codigo aceito — o mesmo codigo
 *   nao entra duas vezes (anti-replay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_ultimo_passo')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_ultimo_passo',
            ]);
        });
    }
};
