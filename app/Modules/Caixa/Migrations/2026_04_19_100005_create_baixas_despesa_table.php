<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baixas_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('despesa_id')->constrained('despesas')->cascadeOnDelete();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->decimal('valor', 10, 2);
            $table->string('forma_pagamento');
            $table->dateTime('data');
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('despesa_id');
            $table->index('caixa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_despesa');
    }
};
