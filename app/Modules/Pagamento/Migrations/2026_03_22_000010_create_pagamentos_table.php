<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos');
            $table->decimal('valor', 10, 2);
            $table->string('forma_pagamento', 20);
            $table->string('status', 20)->default('pendente');
            $table->timestamps();

            $table->index(['conta_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
