<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baixas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->cascadeOnDelete();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->decimal('valor', 10, 2);
            $table->string('forma_pagamento', 20);
            $table->datetime('data');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('pagamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_pagamento');
    }
};
