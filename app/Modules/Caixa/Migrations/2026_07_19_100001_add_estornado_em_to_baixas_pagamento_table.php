<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            // Marcador do estorno no regime "fluxo, nao saldo" (ADR-0011): a baixa E o
            // registro do recebimento; ao cancelar a venda, marcamos a baixa como
            // estornada. O painel do dia por forma neta o recebido pela data do estorno,
            // sem depender de recebivel/contra-lancamento.
            $table->timestamp('estornado_em')->nullable()->after('data');
            $table->index('estornado_em');
        });
    }

    public function down(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            $table->dropIndex(['estornado_em']);
            $table->dropColumn('estornado_em');
        });
    }
};
