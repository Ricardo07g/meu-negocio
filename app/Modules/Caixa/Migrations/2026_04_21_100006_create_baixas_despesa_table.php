<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa de parcela de conta a pagar (pagamento efetivo da despesa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baixas_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('parcela_despesa_id')->constrained('parcelas_despesa')->cascadeOnDelete();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();

            $table->decimal('valor', 10, 2);
            $table->decimal('multa', 10, 2)->default(0);
            $table->decimal('juros', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->string('forma_pagamento', 20);
            $table->dateTime('data');
            $table->text('observacao')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('parcela_despesa_id');
            $table->index('caixa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_despesa');
    }
};
