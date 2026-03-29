<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nome', 200);
            $table->integer('duracao'); // em minutos
            $table->decimal('valor', 10, 2);
            $table->timestamps();

            $table->index(['conta_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
