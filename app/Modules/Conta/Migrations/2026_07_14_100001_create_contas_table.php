<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('tipo', 20); // App\Enums\TipoConta: caixa | banco | carteira
            $table->decimal('saldo_inicial', 10, 2)->default(0);
            $table->boolean('ativo')->default(true);
            // A conta "caixa" da gaveta e a conta destino padrao dos recebiveis de cartao.
            $table->boolean('eh_caixa_padrao')->default(false);
            $table->boolean('eh_destino_recebivel_padrao')->default(false);
            // Metadados opcionais do banco (apenas rotulo, sem integracao).
            $table->string('instituicao', 100)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('numero', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rede_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};
