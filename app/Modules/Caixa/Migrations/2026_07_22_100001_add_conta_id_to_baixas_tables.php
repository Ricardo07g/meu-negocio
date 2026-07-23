<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    /** @var list<string> */
    private array $tabelas = ['baixas_pagamento', 'baixas_despesa'];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                // Conta de destino da baixa (razao unificado, ADR-0010/0011). TODA baixa
                // registra onde o dinheiro caiu — inclusive as que NAO passam pela gaveta
                // (cartao/pix -> banco), para o extrato da conta mostrar o fluxo recebido.
                $table->foreignId('conta_id')->nullable()->after('caixa_id')
                    ->constrained('contas')->nullOnDelete();
                $table->index(['conta_id', 'data']);
            });

            $this->backfill($tabela);
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropIndex(['conta_id', 'data']);
                $table->dropConstrainedForeignId('conta_id');
            });
        }
    }

    /**
     * Preenche conta_id das baixas antigas re-resolvendo o destino (mesma regra do
     * CaixaService::resolverContaDestino). Em PHP para funcionar igual em MySQL e SQLite.
     */
    private function backfill(string $tabela): void
    {
        DB::table($tabela)->whereNull('conta_id')->orderBy('id')->chunkById(500, function ($baixas) use ($tabela) {
            foreach ($baixas as $b) {
                $contaId = $this->resolverConta($b);
                if ($contaId !== null) {
                    DB::table($tabela)->where('id', $b->id)->update(['conta_id' => $contaId]);
                }
            }
        });
    }

    private function resolverConta(object $b): ?int
    {
        // Gaveta: a conta e a da sessao de caixa (dinheiro fisico).
        if (! empty($b->caixa_id)) {
            $contaCaixa = DB::table('caixas')->where('id', $b->caixa_id)->value('conta_id');
            if ($contaCaixa) {
                return (int) $contaCaixa;
            }
        }

        // Nao-gaveta: destino explicito da forma (se for da empresa) -> destino_recebivel_padrao -> caixa_padrao.
        if (! empty($b->forma_pagamento_id)) {
            $destinoForma = DB::table('formas_pagamento')->where('id', $b->forma_pagamento_id)->value('conta_destino_id');
            if ($destinoForma && DB::table('contas')->where('id', $destinoForma)->where('empresa_id', $b->empresa_id)->exists()) {
                return (int) $destinoForma;
            }
        }

        $destino = DB::table('contas')->where('empresa_id', $b->empresa_id)->where('eh_destino_recebivel_padrao', true)->value('id');
        if ($destino) {
            return (int) $destino;
        }

        $caixa = DB::table('contas')->where('empresa_id', $b->empresa_id)->where('eh_caixa_padrao', true)->value('id');

        return $caixa ? (int) $caixa : null;
    }
};
