<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('avulso')->after('valor');
            $table->integer('qtd_sessoes')->nullable()->after('tipo');
            $table->json('dias_semana')->nullable()->after('qtd_sessoes');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'qtd_sessoes', 'dias_semana']);
        });
    }
};
