<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->date('data');
            $table->decimal('saldo_abertura', 10, 2)->default(0);
            $table->decimal('saldo_fechamento', 10, 2)->nullable();
            $table->string('status', 20)->default('aberto');
            $table->text('observacao')->nullable();
            $table->datetime('fechado_em')->nullable();
            $table->foreignId('fechado_por')->nullable()->constrained('usuarios');
            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index(['empresa_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixas');
    }
};
