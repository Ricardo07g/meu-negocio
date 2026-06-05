<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->integer('qtd_etapas')->nullable()->after('tipo');
            $table->text('descricao')->nullable()->after('qtd_etapas');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn(['qtd_etapas', 'descricao']);
        });
    }
};
