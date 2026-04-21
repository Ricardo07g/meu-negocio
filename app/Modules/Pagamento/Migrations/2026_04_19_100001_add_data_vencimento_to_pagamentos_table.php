<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->date('data_vencimento')->nullable()->after('valor_pago');
            $table->index('data_vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropIndex(['data_vencimento']);
            $table->dropColumn('data_vencimento');
        });
    }
};
