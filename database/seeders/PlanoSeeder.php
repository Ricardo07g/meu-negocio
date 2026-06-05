<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tenant\Models\Plano;
use Illuminate\Database\Seeder;

class PlanoSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'nome' => 'free',
                'preco_mensal' => 0,
                'descricao' => 'Comece sem custo. Ideal para autonomos testando o sistema.',
                'max_empresas' => 1,
                'max_usuarios' => 2,
                'tem_estoque' => true,
                'tem_financeiro' => true,
            ],
            [
                'nome' => 'basic',
                'preco_mensal' => 49.90,
                'descricao' => 'Para pequenos negocios em crescimento, com agenda e estoque.',
                'max_empresas' => 2,
                'max_usuarios' => 5,
                'tem_estoque' => true,
                'tem_financeiro' => false,
            ],
            [
                'nome' => 'pro',
                'preco_mensal' => 99.90,
                'descricao' => 'Estrutura completa: agenda, estoque, financeiro e multi-empresa.',
                'max_empresas' => 5,
                'max_usuarios' => 10,
                'tem_estoque' => true,
                'tem_financeiro' => true,
            ],
            [
                'nome' => 'business',
                'preco_mensal' => 199.90,
                'descricao' => 'Sem limites de empresas e usuarios. Para redes consolidadas.',
                'max_empresas' => 0, // ilimitado
                'max_usuarios' => 0, // ilimitado
                'tem_estoque' => true,
                'tem_financeiro' => true,
            ],
        ];

        foreach ($planos as $plano) {
            Plano::updateOrCreate(['nome' => $plano['nome']], $plano);
        }
    }
}
