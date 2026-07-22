<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recebiveis', function (Blueprint $table) {
            // Conta (banco/carteira) em que o recebivel cai quando liquida (razao unificado,
            // ADR-0010). Entra no saldo da conta pela data prevista, sem virar Lancamento.
            $table->foreignId('conta_id')->nullable()->after('empresa_id')
                ->constrained('contas')->nullOnDelete();
            $table->index(['conta_id', 'data_prevista']);
        });
    }

    public function down(): void
    {
        Schema::table('recebiveis', function (Blueprint $table) {
            $table->dropIndex(['conta_id', 'data_prevista']);
            $table->dropConstrainedForeignId('conta_id');
        });
    }
};
