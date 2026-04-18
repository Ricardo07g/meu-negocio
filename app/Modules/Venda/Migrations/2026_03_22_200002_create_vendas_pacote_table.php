<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas_pacote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('servico_id')->constrained('servicos');
            $table->foreignId('atendente_id')->constrained('usuarios');
            $table->date('data');
            $table->decimal('valor_total', 10, 2);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('acrescimo', 10, 2)->default(0);
            $table->integer('qtd_sessoes');
            $table->string('status', 20)->default('ativo');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conta_id', 'empresa_id']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_pacote');
    }
};
