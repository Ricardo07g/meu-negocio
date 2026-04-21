<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            $table->decimal('multa', 10, 2)->default(0)->after('valor');
            $table->decimal('juros', 10, 2)->default(0)->after('multa');
        });
    }

    public function down(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['multa', 'juros']);
        });
    }
};
