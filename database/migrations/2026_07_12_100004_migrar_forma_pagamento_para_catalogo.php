<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migra `forma_pagamento` (enum string) para o catálogo `formas_pagamento`:
 * - adiciona FK `forma_pagamento_id` nas baixas e parcelas (referência viva);
 * - troca a coluna string `forma_pagamento` por `forma_pagamento_nome`
 *   (snapshot histórico do nome da forma) nas baixas, parcelas e movimentos.
 *
 * movimentos_caixa NÃO recebe FK (cartão nunca gera movimento; a coluna string
 * é apenas snapshot). Ambiente local: reseed cobre a perda dos valores antigos.
 */
return new class extends Migration
{
    private array $tabelasComFk = [
        'baixas_pagamento',
        'baixas_despesa',
        'parcelas_pagamento',
        'parcelas_despesa',
    ];

    private array $tabelasSnapshot = [
        'baixas_pagamento',
        'baixas_despesa',
        'parcelas_pagamento',
        'parcelas_despesa',
        'movimentos_caixa',
    ];

    public function up(): void
    {
        foreach ($this->tabelasComFk as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
            });
        }

        foreach ($this->tabelasSnapshot as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('forma_pagamento');
            });
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('forma_pagamento_nome', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelasSnapshot as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('forma_pagamento_nome');
            });
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('forma_pagamento', 20)->nullable();
            });
        }

        foreach ($this->tabelasComFk as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('forma_pagamento_id');
            });
        }
    }
};
