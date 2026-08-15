<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca o encaixe: este atendimento foi agendado fora do expediente, por
     * decisao consciente de quem tinha permissao para forcar.
     *
     * Sem a coluna, "furar o horario" seria indistinguivel de "o expediente
     * mudou depois" — e o encaixe deixaria de ser auditavel.
     */
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->boolean('fora_expediente')->default(false)->after('motivo_sem_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('fora_expediente');
        });
    }
};
