<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ME-001: Pivot N:N entre Empresa e Usuario.
 *
 * Permite que um usuario nao-admin tenha acesso a multiplas empresas dentro
 * da mesma rede. A coluna usuarios.empresa_id e mantida como "empresa default
 * ao logar"; esta pivot e a fonte de verdade do conjunto de empresas que o
 * usuario pode acessar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'usuario_id']);
            $table->index('rede_id');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_usuario');
    }
};
