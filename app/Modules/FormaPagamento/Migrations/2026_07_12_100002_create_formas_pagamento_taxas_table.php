<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento_taxas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento')->cascadeOnDelete();
            // Faixa de numero de parcelas do cartao (ex.: 1-1, 2-6, 7-12).
            $table->unsignedTinyInteger('parcela_min');
            $table->unsignedTinyInteger('parcela_max');
            $table->decimal('taxa_percentual', 5, 2);
            $table->timestamps();

            $table->index('forma_pagamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento_taxas');
    }
};
