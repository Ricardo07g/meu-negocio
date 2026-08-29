<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma tabela, tres papeis — de proposito:
 *
 *  1. **Cache**: `hash_entrada` e a chave. Payload igual => reaproveita, sem chamar o modelo.
 *  2. **Historico**: a analise de hoje ao lado da de tres semanas atras.
 *  3. **Razao de consumo**: a cota diaria e um SUM daqui, sem contador paralelo para
 *     dessincronizar. Por isso as recusas por cota tambem viram linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analises_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            // Alvo polimorfico: Empresa (carteira inteira) ou Cliente (individual).
            $table->morphs('analisavel');

            $table->string('tipo', 40);
            $table->string('status', 20);

            // sha256 do payload ja classificado. Nao contem data crua: se contivesse, o
            // hash mudaria todo dia e o cache nao serviria para nada (ver ADR/plano).
            $table->string('hash_entrada', 64);
            $table->string('versao_prompt', 20)->default('v1');
            $table->string('modelo', 120);

            $table->json('resultado')->nullable();
            $table->text('erro')->nullable();

            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_saida')->default(0);
            $table->decimal('custo_estimado', 12, 6)->default(0);
            $table->unsignedInteger('duracao_ms')->default(0);

            // Incrementado a cada cache hit: e daqui que sai a taxa de acerto.
            $table->unsignedInteger('reaproveitacoes')->default(0);
            $table->timestamp('ultima_reaproveitacao_em')->nullable();

            $table->timestamps();

            // Soma da cota diaria por empresa.
            $table->index(['empresa_id', 'created_at'], 'analises_ia_empresa_data_idx');
            // Lookup do cache.
            $table->index(
                ['analisavel_type', 'analisavel_id', 'tipo', 'hash_entrada'],
                'analises_ia_cache_idx'
            );
            $table->index('rede_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analises_ia');
    }
};
