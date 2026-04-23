<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela Despesa atua como "título" (contas a pagar).
 * Valor a pagar, vencimentos e status ficam em `parcelas_despesa`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_despesa_id')->nullable()->constrained('categorias_despesa')->nullOnDelete();

            $table->string('nome', 200);
            $table->string('fornecedor_nome', 150)->nullable();
            $table->string('documento', 80)->nullable();
            $table->text('observacoes')->nullable();

            $table->decimal('valor_total', 10, 2);

            $table->string('condicao_pagamento', 20);    // a_vista | a_prazo
            $table->string('forma_recebimento_prazo', 20)->nullable(); // carnê (futuro: boleto, pix_parcelado)
            $table->date('mes_referencia');               // sempre dia 1 do mês de competência
            $table->date('data_emissao');

            $table->string('status', 20)->default('pendente'); // pendente | parcial | paga | cancelada

            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('categoria_despesa_id');
            $table->index('status');
            $table->index('mes_referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
    }
};
