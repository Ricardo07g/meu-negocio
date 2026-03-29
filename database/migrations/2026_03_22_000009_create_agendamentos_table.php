<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('servico_id')->constrained('servicos');
            $table->foreignId('profissional_id')->constrained('profissionais');
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('status', 20)->default('agendado');
            $table->timestamps();

            $table->index(['conta_id', 'empresa_id']);
            $table->index(['profissional_id', 'inicio', 'fim']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
