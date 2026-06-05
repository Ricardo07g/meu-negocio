<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa de parcela de contas a receber (recebimento efetivo).
 * Registra principal + multa + juros e amarra ao caixa do dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baixas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('parcela_pagamento_id')->constrained('parcelas_pagamento')->cascadeOnDelete();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();

            $table->decimal('valor', 10, 2);
            $table->decimal('multa', 10, 2)->default(0);
            $table->decimal('juros', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->string('forma_pagamento', 20);
            $table->dateTime('data');
            $table->text('observacao')->nullable();

            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('parcela_pagamento_id');
            $table->index('caixa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_pagamento');
    }
};
