<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela Pagamento atua como "título" (contas a receber).
 * O valor a cobrar, vencimentos e status individuais ficam em `parcelas_pagamento`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            // Origem (exclusivas)
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('venda_etapas_id')->nullable()->constrained('vendas_etapas')->nullOnDelete();
            $table->foreignId('venda_produto_id')->nullable()->constrained('vendas_produto')->nullOnDelete();

            $table->decimal('valor_total', 10, 2);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('acrescimo', 10, 2)->default(0);

            $table->string('condicao_pagamento', 20);   // a_vista | a_prazo
            $table->string('forma_recebimento_prazo', 20)->nullable(); // carnê (futuro: boleto, pix_parcelado)
            $table->date('mes_referencia');              // sempre dia 1 do mês de competência

            $table->string('status', 20)->default('pendente'); // pendente | parcial | pago | cancelado | estornado
            $table->text('descricao')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
            $table->index('cliente_id');
            $table->index('status');
            $table->index('mes_referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
