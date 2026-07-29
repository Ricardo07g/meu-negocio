<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tenant\Models\Plano;
use Illuminate\Database\Seeder;

/**
 * Duas licencas apenas, e o gratuito e subconjunto estrito do pago.
 *
 * O plano vale por EMPRESA (licenca), nao por rede — por isso nao ha `max_empresas`:
 * contratar outra unidade e contratar outra licenca.
 */
class PlanoSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'slug' => Plano::GRATIS,
                'nome' => 'Grátis',
                'preco_por_licenca' => 0,
                'descricao' => 'Uma unidade, sem custo. Clientes, servicos, produtos, agenda e vendas.',
                'max_usuarios' => 2,
                'tem_estoque' => false,
                'tem_financeiro' => false,
            ],
            [
                'slug' => Plano::PRO,
                'nome' => 'Pro',
                'preco_por_licenca' => 79.90,
                'descricao' => 'Licenca completa por unidade: estoque, financeiro e mais usuarios.',
                'max_usuarios' => 15,
                'tem_estoque' => true,
                'tem_financeiro' => true,
            ],
        ];

        foreach ($planos as $plano) {
            Plano::updateOrCreate(['slug' => $plano['slug']], $plano);
        }
    }
}
