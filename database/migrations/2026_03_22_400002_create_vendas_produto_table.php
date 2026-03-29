<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas_produto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('produto_id')->constrained('produtos');
            $table->integer('quantidade');
            $table->decimal('valor_total', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_produto');
    }
};
