<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            // Sessao de caixa diaria: setado so nos lancamentos da conta caixa fisica (contagem de gaveta).
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->string('tipo', 20); // App\Enums\TipoLancamento: credito | debito
            // Rotulo do movimento p/ exibicao: movimento | sangria | reforco | abertura | ajuste | transferencia.
            $table->string('categoria', 20)->default('movimento');
            $table->decimal('valor', 10, 2);
            $table->date('data');
            $table->string('descricao', 255);
            $table->string('forma_pagamento_nome', 100)->nullable(); // snapshot p/ historico
            // Origem do lancamento (o que o gerou).
            $table->foreignId('baixa_pagamento_id')->nullable()->constrained('baixas_pagamento')->nullOnDelete();
            $table->foreignId('baixa_despesa_id')->nullable()->constrained('baixas_despesa')->nullOnDelete();
            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index(['conta_id', 'data']);
            $table->index('caixa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
