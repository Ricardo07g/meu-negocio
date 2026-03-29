<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->foreignId('venda_pacote_id')->nullable()->after('agendamento_id')->constrained('vendas_pacote');
            $table->foreignId('venda_produto_id')->nullable()->after('venda_pacote_id')->constrained('vendas_produto');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropForeign(['venda_pacote_id']);
            $table->dropForeign(['venda_produto_id']);
            $table->dropColumn(['venda_pacote_id', 'venda_produto_id']);
        });
    }
};
