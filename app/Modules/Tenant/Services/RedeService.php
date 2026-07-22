<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Services;

use App\Enums\StatusRede;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\{CategoriaProduto, Produto};
use App\Modules\Servico\Models\Servico;
use App\Modules\Tenant\Actions\CriarEmpresaAction;
use App\Modules\Tenant\DTOs\{CriarRedeData, EmpresaData};
use App\Modules\Tenant\Models\{Plano, Rede};
use App\Modules\Usuario\Actions\CriarUsuarioAction;
use App\Modules\Usuario\DTOs\UsuarioData;
use Illuminate\Support\Facades\DB;

class RedeService
{
    public function __construct(
        private CriarEmpresaAction $criarEmpresa,
        private CriarUsuarioAction $criarUsuario,
    ) {}

    public function criar(CriarRedeData $data, UsuarioData $usuarioData): Rede
    {
        return DB::transaction(function () use ($data, $usuarioData) {
            $planoFree = Plano::where('nome', 'free')->firstOrFail();

            $rede = Rede::create([
                'nome' => $data->nome,
                'plano_id' => $data->plano_id ?? $planoFree->id,
                'status' => StatusRede::Ativa,
            ]);

            $empresa = $this->criarEmpresa->executar(
                $rede,
                new EmpresaData(nome: $data->nome)
            );

            $usuario = $this->criarUsuario->executar(
                $rede,
                new UsuarioData(
                    nome: $usuarioData->nome,
                    email: $usuarioData->email,
                    password: $usuarioData->password,
                    empresa_id: $empresa->id,
                    papel: 'Admin',
                )
            );

            // Categorias de produto padrão
            $categoriasPadrao = ['Cabelo', 'Corpo', 'Rosto', 'Unhas', 'Consumíveis', 'Outros'];
            $categorias = [];
            foreach ($categoriasPadrao as $descricao) {
                $categorias[$descricao] = CategoriaProduto::create(['rede_id' => $rede->id, 'descricao' => $descricao]);
            }

            // Produtos padrão
            $produtosPadrao = [
                ['nome' => 'Shampoo Profissional', 'valor_venda' => 45.00, 'valor_custo' => 22.00, 'quantidade' => 20, 'categoria' => 'Cabelo'],
                ['nome' => 'Condicionador Profissional', 'valor_venda' => 42.00, 'valor_custo' => 20.00, 'quantidade' => 15, 'categoria' => 'Cabelo'],
                ['nome' => 'Óleo Capilar', 'valor_venda' => 35.00, 'valor_custo' => 15.00, 'quantidade' => 10, 'categoria' => 'Cabelo'],
                ['nome' => 'Creme Hidratante Corporal', 'valor_venda' => 55.00, 'valor_custo' => 28.00, 'quantidade' => 12, 'categoria' => 'Corpo'],
                ['nome' => 'Protetor Solar FPS 50', 'valor_venda' => 65.00, 'valor_custo' => 35.00, 'quantidade' => 8, 'categoria' => 'Rosto'],
                ['nome' => 'Esmalte Gel', 'valor_venda' => 18.00, 'valor_custo' => 7.00, 'quantidade' => 30, 'categoria' => 'Unhas'],
            ];
            foreach ($produtosPadrao as $p) {
                Produto::create([
                    'rede_id' => $rede->id,
                    'nome' => $p['nome'],
                    'valor_venda' => $p['valor_venda'],
                    'valor_custo' => $p['valor_custo'],
                    'quantidade' => $p['quantidade'],
                    'categoria_produto_id' => $categorias[$p['categoria']]->id,
                    'ativo' => true,
                ]);
            }

            // Serviços padrão
            $servicosPadrao = [
                ['nome' => 'Corte Masculino', 'duracao' => 30, 'valor' => 45.00, 'tipo' => 'unico'],
                ['nome' => 'Corte Feminino', 'duracao' => 60, 'valor' => 75.00, 'tipo' => 'unico'],
                ['nome' => 'Escova Progressiva', 'duracao' => 120, 'valor' => 200.00, 'tipo' => 'unico'],
                ['nome' => 'Manicure', 'duracao' => 45, 'valor' => 35.00, 'tipo' => 'unico'],
                ['nome' => 'Massagem Relaxante', 'duracao' => 60, 'valor' => 120.00, 'tipo' => 'unico'],
                ['nome' => 'Massagem 10 Sessões', 'duracao' => 60, 'valor' => 1000.00, 'tipo' => 'etapas', 'qtd_etapas' => 10],
            ];
            foreach ($servicosPadrao as $s) {
                Servico::create([
                    'rede_id' => $rede->id,
                    'nome' => $s['nome'],
                    'duracao' => $s['duracao'],
                    'valor' => $s['valor'],
                    'tipo' => $s['tipo'],
                    'qtd_etapas' => $s['qtd_etapas'] ?? null,
                ]);
            }

            // Clientes padrão
            $clientesPadrao = [
                ['nome' => 'Maria Silva', 'telefone' => '(11) 99999-0001', 'email' => 'maria@teste.com'],
                ['nome' => 'João Santos', 'telefone' => '(11) 99999-0002', 'email' => 'joao@teste.com'],
                ['nome' => 'Ana Oliveira', 'telefone' => '(11) 99999-0003', 'email' => 'ana@teste.com'],
                ['nome' => 'Carlos Souza', 'telefone' => '(11) 99999-0004'],
                ['nome' => 'Fernanda Lima', 'telefone' => '(11) 99999-0005'],
            ];
            foreach ($clientesPadrao as $c) {
                Cliente::create([
                    'rede_id' => $rede->id,
                    'nome' => $c['nome'],
                    'telefone' => $c['telefone'],
                    'email' => $c['email'] ?? null,
                ]);
            }

            // Formas de pagamento e contas padrão nascem com a empresa (CriarEmpresaAction).

            $rede->setRelation('usuarioCriado', $usuario);

            return $rede;
        });
    }
}
