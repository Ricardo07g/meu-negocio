<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De 4 planos (free/basic/pro/business) para 2 (gratis/pro).
 *
 * A regua antiga nao era monotonica: `free` (R$ 0) tinha `tem_financeiro = true` e
 * `basic` (R$ 49,90, pago) tinha `tem_financeiro = false` — pagar removia um modulo.
 *
 * Reaponta antes de remover, senao as FKs de `redes` e `faturas` quebram. Planos pagos
 * (basic/business) sobem para Pro em vez de descer: rebaixar tiraria modulos de quem ja pagava.
 */
return new class extends Migration
{
    /** Definicao canonica das duas licencas — espelhada em `Database\Seeders\PlanoSeeder`. */
    private const DESTINOS = [
        'gratis' => [
            'nome' => 'Grátis',
            'preco_por_licenca' => 0,
            'descricao' => 'Uma unidade, sem custo. Clientes, servicos, produtos, agenda e vendas.',
            'max_usuarios' => 2,
            'tem_estoque' => false,
            'tem_financeiro' => false,
        ],
        'pro' => [
            'nome' => 'Pro',
            'preco_por_licenca' => 79.90,
            'descricao' => 'Licenca completa por unidade: estoque, financeiro e mais usuarios.',
            'max_usuarios' => 15,
            'tem_estoque' => true,
            'tem_financeiro' => true,
        ],
    ];

    public function up(): void
    {
        $agora = now();

        foreach (self::DESTINOS as $slug => $dados) {
            $existe = DB::table('planos')->where('slug', $slug)->exists();

            if ($existe) {
                DB::table('planos')->where('slug', $slug)->update($dados + ['updated_at' => $agora]);

                continue;
            }

            DB::table('planos')->insert(
                $dados + ['slug' => $slug, 'created_at' => $agora, 'updated_at' => $agora]
            );
        }

        $idGratis = (int) DB::table('planos')->where('slug', 'gratis')->value('id');
        $idPro = (int) DB::table('planos')->where('slug', 'pro')->value('id');

        $mapa = ['free' => $idGratis, 'basic' => $idPro, 'business' => $idPro];

        foreach ($mapa as $slugObsoleto => $idDestino) {
            $idObsoleto = DB::table('planos')->where('slug', $slugObsoleto)->value('id');

            if ($idObsoleto === null) {
                continue;
            }

            DB::table('redes')->where('plano_id', $idObsoleto)->update(['plano_id' => $idDestino]);
            DB::table('faturas')->where('plano_id', $idObsoleto)->update(['plano_id' => $idDestino]);
            DB::table('planos')->where('id', $idObsoleto)->delete();
        }
    }

    /**
     * Recria as linhas obsoletas com os valores originais. O reapontamento em si e
     * irreversivel: a informacao de qual rede estava em qual plano antigo foi consolidada.
     */
    public function down(): void
    {
        $agora = now();

        $originais = [
            ['slug' => 'free', 'nome' => 'free', 'preco_por_licenca' => 0, 'max_usuarios' => 2, 'tem_estoque' => true, 'tem_financeiro' => true],
            ['slug' => 'basic', 'nome' => 'basic', 'preco_por_licenca' => 49.90, 'max_usuarios' => 5, 'tem_estoque' => true, 'tem_financeiro' => false],
            ['slug' => 'business', 'nome' => 'business', 'preco_por_licenca' => 199.90, 'max_usuarios' => 0, 'tem_estoque' => true, 'tem_financeiro' => true],
        ];

        foreach ($originais as $plano) {
            if (DB::table('planos')->where('slug', $plano['slug'])->exists()) {
                continue;
            }

            DB::table('planos')->insert($plano + ['created_at' => $agora, 'updated_at' => $agora]);
        }
    }
};
