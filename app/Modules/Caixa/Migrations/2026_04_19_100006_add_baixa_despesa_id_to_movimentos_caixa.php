<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentos_caixa', function (Blueprint $table) {
            $table->foreignId('baixa_despesa_id')
                ->nullable()
                ->after('despesa_id')
                ->constrained('baixas_despesa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimentos_caixa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('baixa_despesa_id');
        });
    }
};
