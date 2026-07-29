<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Services;

use App\Enums\StatusRede;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Produto\Models\{CategoriaProduto, Produto};
use App\Modules\Servico\Models\Servico;
use App\Modules\Tenant\Actions\CriarEmpresaAction;
use App\Modules\Tenant\DTOs\{CriarRedeData, EmpresaData};
use App\Modules\Tenant\Models\Rede;
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
            $rede = Rede::create([
                'nome' => $data->nome,
                'status' => StatusRede::Ativa,
            ]);

            // A primeira unidade nasce no Pro em teste gratuito (a Action cuida do prazo)
            // e ja com contas e formas de pagamento padrao.
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

            $this->semearExemplos($rede);

            $rede->setRelation('usuarioCriado', $usuario);

            return $rede;
        });
    }

    /**
     * Um exemplo de cada, so para a conta nao abrir vazia e o usuario entender o formato.
     *
     * Catalogo de demonstracao poluido (dezenas de produtos e clientes ficticios de salao)
     * so gera trabalho de faxina para quem acabou de se cadastrar. Contas e formas de
     * pagamento NAO entram aqui: sao infraestrutura da empresa e nascem no CriarEmpresaAction.
     */
    private function semearExemplos(Rede $rede): void
    {
        $categoria = CategoriaProduto::create([
            'rede_id' => $rede->id,
            'descricao' => 'Geral',
        ]);

        Produto::create([
            'rede_id' => $rede->id,
            'nome' => 'Produto exemplo',
            'valor_venda' => 50.00,
            'valor_custo' => 25.00,
            'quantidade' => 10,
            'categoria_produto_id' => $categoria->id,
            'ativo' => true,
        ]);

        Servico::create([
            'rede_id' => $rede->id,
            'nome' => 'Serviço exemplo',
            'duracao' => 60,
            'valor' => 100.00,
            'tipo' => 'unico',
            'qtd_etapas' => null,
        ]);

        Cliente::create([
            'rede_id' => $rede->id,
            'nome' => 'Cliente exemplo',
            'telefone' => null,
            'email' => null,
        ]);
    }
}
