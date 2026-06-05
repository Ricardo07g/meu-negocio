<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->decimal('preco_mensal', 8, 2)->default(0)->after('nome');
            $table->string('descricao', 255)->nullable()->after('preco_mensal');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['preco_mensal', 'descricao']);
        });
    }
};
