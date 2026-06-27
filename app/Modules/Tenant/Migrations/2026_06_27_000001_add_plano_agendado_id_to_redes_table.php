<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redes', function (Blueprint $table) {
            if (! Schema::hasColumn('redes', 'plano_agendado_id')) {
                // Downgrade agendado: o plano so passa a valer na virada do mes.
                // Referencia opcional -> nullOnDelete (ADR-0006).
                $table->foreignId('plano_agendado_id')
                    ->nullable()
                    ->after('plano_id')
                    ->constrained('planos')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('redes', function (Blueprint $table) {
            if (Schema::hasColumn('redes', 'plano_agendado_id')) {
                $table->dropForeign(['plano_agendado_id']);
                $table->dropColumn('plano_agendado_id');
            }
        });
    }
};
