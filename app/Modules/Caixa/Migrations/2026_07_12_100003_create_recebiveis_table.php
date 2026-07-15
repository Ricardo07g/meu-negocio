<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recebiveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento');
            $table->foreignId('baixa_pagamento_id')->nullable()->constrained('baixas_pagamento')->nullOnDelete();
            $table->string('descricao', 255);
            $table->decimal('valor_bruto', 10, 2);
            $table->decimal('taxa_percentual', 5, 2)->default(0);
            $table->decimal('valor_liquido', 10, 2);
            $table->unsignedTinyInteger('parcela_numero')->default(1);
            $table->unsignedTinyInteger('parcela_total')->default(1);
            $table->date('data_venda');
            $table->date('data_prevista');
            $table->dateTime('cancelado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('data_prevista');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recebiveis');
    }
};
