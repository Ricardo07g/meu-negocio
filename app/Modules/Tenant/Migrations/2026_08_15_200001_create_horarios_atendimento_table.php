<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expediente da unidade: a janela em que a agenda aceita atendimento.
     *
     * Ate aqui isso nao existia em lugar nenhum — o `hourStart: 8 / hourEnd: 21`
     * do calendario e janela de VISUALIZACAO do Toast UI, e o servidor nunca
     * olhou a hora. Dava para agendar 23:40 de domingo sem um "nao" no caminho.
     *
     * `usuario_id` nulo = expediente da empresa inteira. A coluna ja nasce para
     * o horario por atendente (cada profissional com sua janela): a v1 so expoe
     * a UI da empresa, mas o resolvedor ja prefere a linha do atendente quando
     * ela existe — sem isso, adicionar depois exigiria migrar dados.
     */
    public function up(): void
    {
        Schema::create('horarios_atendimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rede_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0 = domingo … 6 = sabado
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['rede_id', 'empresa_id']);
            $table->index(['empresa_id', 'usuario_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_atendimento');
    }
};
