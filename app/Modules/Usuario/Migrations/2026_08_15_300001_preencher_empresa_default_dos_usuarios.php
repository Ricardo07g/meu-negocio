<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dá empresa default a quem ficou sem.
     *
     * O formulário de usuário nunca teve o campo `empresa_id` — só os checkboxes
     * do pivot `empresa_usuario` —, então todo funcionário criado pela tela
     * nasceu com a coluna nula. Isso deixava o usuário sem unidade para abrir no
     * login e, junto com a `UsuarioPolicy` antiga, tornava esses cadastros
     * impossíveis de editar (403 até para o Admin).
     *
     * Preenche com a primeira unidade a que o usuário já tem acesso: é
     * preferência, não permissão, e o pivot continua sendo a fonte de verdade.
     */
    public function up(): void
    {
        $semDefault = DB::table('usuarios')
            ->whereNull('empresa_id')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($semDefault as $usuarioId) {
            $empresaId = DB::table('empresa_usuario')
                ->where('usuario_id', $usuarioId)
                ->orderBy('empresa_id')
                ->value('empresa_id');

            if ($empresaId !== null) {
                DB::table('usuarios')->where('id', $usuarioId)->update(['empresa_id' => $empresaId]);
            }
        }
    }

    /**
     * Não desfaz: devolver a coluna para null recriaria exatamente o estado que
     * causa o 403, e não há como distinguir o que esta migration preencheu do
     * que o usuário escolheu depois.
     */
    public function down(): void {}
};
