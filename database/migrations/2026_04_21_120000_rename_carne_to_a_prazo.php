<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renomeia o valor legado 'carne' da condicao_pagamento para 'a_prazo'
 * nas tabelas que carregam titulos (pagamentos e despesas).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pagamentos')
            ->where('condicao_pagamento', 'carne')
            ->update(['condicao_pagamento' => 'a_prazo']);

        DB::table('despesas')
            ->where('condicao_pagamento', 'carne')
            ->update(['condicao_pagamento' => 'a_prazo']);
    }

    public function down(): void
    {
        DB::table('pagamentos')
            ->where('condicao_pagamento', 'a_prazo')
            ->update(['condicao_pagamento' => 'carne']);

        DB::table('despesas')
            ->where('condicao_pagamento', 'a_prazo')
            ->update(['condicao_pagamento' => 'carne']);
    }
};
