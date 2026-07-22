<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a tabela movimentos_caixa: o razao unificado (ADR-0010) a substitui por
 * `lancamentos` (com caixa_id ligando a sessao da gaveta). down() recria o schema
 * no estado imediatamente anterior (ja com forma_pagamento_nome).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('movimentos_caixa');
    }

    public function down(): void
    {
        Schema::create('movimentos_caixa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->string('tipo', 20); // entrada | saida | sangria | reforco
            $table->decimal('valor', 10, 2);
            $table->string('descricao', 255);
            $table->string('forma_pagamento_nome', 100)->nullable();
            $table->foreignId('baixa_pagamento_id')->nullable()->constrained('baixas_pagamento')->nullOnDelete();
            $table->foreignId('baixa_despesa_id')->nullable()->constrained('baixas_despesa')->nullOnDelete();
            $table->timestamps();

            $table->index('caixa_id');
            $table->index('tipo');
        });
    }
};
