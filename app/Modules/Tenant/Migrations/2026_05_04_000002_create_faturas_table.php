<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes');
            $table->foreignId('plano_id')->constrained('planos');
            $table->string('referencia', 7); // YYYY-MM
            $table->decimal('valor', 8, 2);
            $table->date('vencimento');
            $table->timestamp('pago_em')->nullable();
            $table->string('status', 20)->default('em_aberto');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rede_id', 'referencia']);
            $table->index(['rede_id', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
