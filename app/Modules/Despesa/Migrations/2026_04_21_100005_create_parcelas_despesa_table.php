<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcela individual de uma Despesa (contas a pagar).
 * Espelha a estrutura de parcelas_pagamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcelas_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('despesa_id')->constrained('despesas')->cascadeOnDelete();

            $table->unsignedSmallInteger('numero');
            $table->unsignedSmallInteger('total');

            $table->decimal('valor', 10, 2);
            $table->decimal('valor_pago', 10, 2)->default(0);

            $table->date('data_vencimento');
            $table->string('forma_pagamento', 20)->nullable();

            $table->string('status', 20)->default('pendente'); // pendente|pago|vencido|cancelado|renegociado
            $table->text('observacao')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['despesa_id', 'numero']);
            $table->index(['rede_id', 'empresa_id']);
            $table->index('data_vencimento');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelas_despesa');
    }
};
