<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();

            // Dono polimorfico (Produto, Cliente, Servico, Usuario, ...).
            $table->morphs('anexavel');

            $table->string('colecao', 50)->default('default');
            $table->string('disco', 30)->default('r2');
            $table->string('caminho', 600);
            $table->string('caminho_thumb', 600)->nullable();

            $table->string('nome_original');
            $table->string('extensao', 20);
            $table->string('mime', 120);
            $table->unsignedBigInteger('tamanho')->default(0);

            // Preenchidos apenas para imagens.
            $table->unsignedInteger('largura')->nullable();
            $table->unsignedInteger('altura')->nullable();

            $table->string('hash', 64)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('principal')->default(false);
            $table->json('metadados')->nullable();

            $table->timestamps();

            $table->index(['anexavel_type', 'anexavel_id', 'colecao'], 'arquivos_anexavel_colecao_idx');
            $table->index('rede_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos');
    }
};
