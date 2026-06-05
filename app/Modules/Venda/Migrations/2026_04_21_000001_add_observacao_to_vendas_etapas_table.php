<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_etapas', function (Blueprint $table) {
            $table->text('observacao')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_etapas', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });
    }
};
