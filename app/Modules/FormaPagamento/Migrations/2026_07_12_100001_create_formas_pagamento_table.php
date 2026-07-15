<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('tipo', 20);
            $table->boolean('ativo')->default(true);
            // Se o dinheiro NAO entra na gaveta do caixa na hora — vira recebivel (D+N, liquido de taxa).
            $table->boolean('gera_recebivel')->default(false);
            $table->unsignedSmallInteger('dias_liquidacao')->default(0);
            $table->decimal('taxa_percentual', 5, 2)->default(0);
            $table->boolean('permite_parcelas')->default(false);
            $table->unsignedTinyInteger('max_parcelas')->nullable();
            // Antecipacao (cartao): adianta os recebiveis do adquirente com um custo mensal.
            $table->boolean('antecipacao_automatica')->default(false);
            $table->decimal('taxa_antecipacao_mensal', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento');
    }
};
