<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->integer('qtd_sessoes')->nullable()->after('tipo');
            $table->text('descricao')->nullable()->after('qtd_sessoes');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn(['qtd_sessoes', 'descricao']);
        });
    }
};
