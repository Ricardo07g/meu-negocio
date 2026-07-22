<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            // O caixa diario passa a ser uma sessao da conta do tipo caixa (a gaveta):
            // seus movimentos viram Lancamentos ligados a esta conta (razao unificado, ADR-0010).
            $table->foreignId('conta_id')->nullable()->after('empresa_id')
                ->constrained('contas')->nullOnDelete();
        });

        // Backfill idempotente: liga cada caixa a conta-caixa padrao da sua empresa.
        DB::table('contas')
            ->where('eh_caixa_padrao', true)
            ->whereNull('deleted_at')
            ->get(['id', 'empresa_id'])
            ->each(function ($conta) {
                DB::table('caixas')
                    ->where('empresa_id', $conta->empresa_id)
                    ->whereNull('conta_id')
                    ->update(['conta_id' => $conta->id]);
            });
    }

    public function down(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conta_id');
        });
    }
};
