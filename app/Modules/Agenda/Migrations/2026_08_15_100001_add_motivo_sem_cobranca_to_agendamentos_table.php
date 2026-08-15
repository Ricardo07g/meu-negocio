<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finalizar um atendimento passa a exigir um desfecho declarado: ou vira
     * cobranca (titulo em `pagamentos.agendamento_id`), ou fica registrado por
     * que nao virou. Sem esta coluna, "atendi e nao cobrei" era indistinguivel
     * de "esqueci de cobrar" — os dois viravam um agendamento finalizado e mudo.
     */
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('motivo_sem_cobranca', 20)->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('motivo_sem_cobranca');
        });
    }
};
