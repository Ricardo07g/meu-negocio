<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_caixa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->string('tipo', 20); // entrada, saida, sangria, reforco
            $table->decimal('valor', 10, 2);
            $table->string('descricao', 255);
            $table->string('forma_pagamento', 20)->nullable();
            $table->foreignId('baixa_pagamento_id')->nullable()->constrained('baixas_pagamento')->nullOnDelete();
            $table->foreignId('despesa_id')->nullable()->constrained('despesas')->nullOnDelete();
            $table->timestamps();

            $table->index('caixa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_caixa');
    }
};
