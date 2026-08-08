<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de parcelas do cartao no recebimento (ex.: "Cartao de Credito 2x").
 *
 * A UI da venda ja coletava esse dado (`recebimentos[i][parcelas_cartao]`) e o
 * motor de baixa o descartava: o antigo lar era `recebiveis.parcela_total`, que o
 * ADR-0011 aposentou sem dar substituto. Resultado: o lojista escolhia "2x" e nunca
 * mais via essa informacao em tela nenhuma.
 *
 * A Baixa e o lugar certo: o ADR-0011 a define como "a verdade do recebimento por
 * forma", e o parcelamento e atributo desse recebimento. Puramente INFORMATIVO —
 * nao deriva datas, valores liquidos nem saldo (respeita a decisao 4 do ADR-0011:
 * repasse/liquidacao e dominio do banco, nao nosso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            $table->unsignedTinyInteger('parcelas_cartao')->nullable()->after('forma_pagamento_nome');
        });
    }

    public function down(): void
    {
        Schema::table('baixas_pagamento', function (Blueprint $table) {
            $table->dropColumn('parcelas_cartao');
        });
    }
};
