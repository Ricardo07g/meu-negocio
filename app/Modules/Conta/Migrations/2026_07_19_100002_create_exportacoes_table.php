<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios'); // solicitante
            $table->string('formato', 10);                            // csv / xlsx
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->string('status', 20)->default('processando');     // processando / pronto / erro
            $table->string('disco', 30)->nullable();
            $table->string('caminho', 500)->nullable();
            $table->string('nome_arquivo', 255)->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();        // bytes
            $table->text('erro')->nullable();
            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index(['conta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacoes');
    }
};
