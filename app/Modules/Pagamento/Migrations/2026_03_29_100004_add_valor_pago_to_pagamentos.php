<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('empresa_id')->constrained('clientes')->nullOnDelete();
            $table->decimal('valor_pago', 10, 2)->default(0)->after('valor');
            $table->decimal('desconto', 10, 2)->default(0)->after('valor_pago');
            $table->decimal('acrescimo', 10, 2)->default(0)->after('desconto');
            $table->text('descricao')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropColumn(['valor_pago', 'desconto', 'acrescimo', 'descricao']);
        });
    }
};
