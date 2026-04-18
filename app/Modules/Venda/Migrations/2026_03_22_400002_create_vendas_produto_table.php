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
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->date('data');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('acrescimo', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2);
            $table->string('status', 20)->default('ativa');
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
        });

        Schema::create('venda_produto_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_produto_id')->constrained('vendas_produto')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->string('descricao', 200);
            $table->integer('quantidade');
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('acrescimo', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_produto_itens');
        Schema::dropIfExists('vendas_produto');
    }
};
