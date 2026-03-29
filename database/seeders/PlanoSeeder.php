<?php

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
                'max_empresas' => 1,
                'max_usuarios' => 2,
                'tem_estoque' => true,
                'tem_financeiro' => true,
                'tem_relatorios' => true,
            ],
            [
                'nome' => 'basic',
                'max_empresas' => 2,
                'max_usuarios' => 5,
                'tem_estoque' => true,
                'tem_financeiro' => false,
                'tem_relatorios' => false,
            ],
            [
                'nome' => 'pro',
                'max_empresas' => 5,
                'max_usuarios' => 10,
                'tem_estoque' => true,
                'tem_financeiro' => true,
                'tem_relatorios' => false,
            ],
            [
                'nome' => 'business',
                'max_empresas' => 0, // ilimitado
                'max_usuarios' => 0, // ilimitado
                'tem_estoque' => true,
                'tem_financeiro' => true,
                'tem_relatorios' => true,
            ],
        ];

        foreach ($planos as $plano) {
            Plano::firstOrCreate(['nome' => $plano['nome']], $plano);
        }
    }
}
