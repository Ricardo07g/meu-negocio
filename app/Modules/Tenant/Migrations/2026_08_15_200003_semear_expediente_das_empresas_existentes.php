<?php

declare(strict_types=1);

use App\Modules\Tenant\Models\HorarioAtendimento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dá expediente às unidades que já existiam.
     *
     * `CriarEmpresaAction` semeia o expediente das novas, mas as antigas
     * nasceram antes desta tabela existir. Sem o backfill elas cairiam na rede
     * de segurança do validador ("sem configuração, não restringe") e a feature
     * simplesmente não valeria para ninguém que já usa o sistema — que é
     * justamente quem reclamou de agendar fora do horário.
     *
     * Usa query builder em vez do model de propósito: migration não deve
     * depender de global scope nem de sessão de usuário.
     */
    public function up(): void
    {
        $agora = now();

        $semExpediente = DB::table('empresas')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('horarios_atendimento')
                    ->whereColumn('horarios_atendimento.empresa_id', 'empresas.id');
            })
            ->get(['id', 'rede_id']);

        foreach ($semExpediente as $empresa) {
            $linhas = [];

            foreach (HorarioAtendimento::PADRAO as $dia => [$inicio, $fim, $ativo]) {
                $linhas[] = [
                    'rede_id' => $empresa->rede_id,
                    'empresa_id' => $empresa->id,
                    'usuario_id' => null,
                    'dia_semana' => $dia,
                    'hora_inicio' => $inicio,
                    'hora_fim' => $fim,
                    'ativo' => $ativo,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }

            DB::table('horarios_atendimento')->insert($linhas);
        }
    }

    /**
     * Não desfaz: apagar expediente aqui removeria também o que o usuário
     * ajustou depois. O `down()` da migration da tabela já cobre o rollback
     * completo, e é o único ponto em que apagar é seguro.
     */
    public function down(): void {}
};
