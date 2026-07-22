<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            // Conta destino para onde o dinheiro desta forma vai (razao unificado, ADR-0010).
            // Nullable: quando nao definida, o motor resolve pela natureza da forma (conta
            // caixa padrao ou conta destino de recebivel padrao da empresa).
            $table->foreignId('conta_destino_id')->nullable()->after('empresa_id')
                ->constrained('contas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conta_destino_id');
        });
    }
};
