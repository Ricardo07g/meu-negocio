<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Actions\CriarEmpresaAction;
use App\Modules\Usuario\Actions\CriarUsuarioAction;
use App\Modules\Tenant\DTOs\AtualizarRedeData;
use App\Modules\Tenant\DTOs\CriarRedeData;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Usuario\DTOs\CriarUsuarioData;
use App\Enums\StatusRede;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Tenant\Models\Plano;
use App\Modules\Tenant\Models\Rede;
use Illuminate\Support\Facades\DB;

class RedeService
{
    public function __construct(
        private CriarEmpresaAction $criarEmpresa,
        private CriarUsuarioAction $criarUsuario,
    ) {}

    public function criar(CriarRedeData $data, CriarUsuarioData $usuarioData): Rede
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
                new CriarUsuarioData(
                    nome: $usuarioData->nome,
                    email: $usuarioData->email,
                    password: $usuarioData->password,
                    empresa_id: $empresa->id,
                    papel: 'Admin',
                )
            );

            // Categorias de produto padrão
            $categoriasPadrao = ['Cabelo', 'Corpo', 'Rosto', 'Unhas', 'Consumíveis', 'Outros'];
            foreach ($categoriasPadrao as $nome) {
                CategoriaProduto::create(['rede_id' => $rede->id, 'nome' => $nome]);
            }

            $rede->setRelation('usuarioCriado', $usuario);

            return $rede;
        });
    }

    public function atualizar(Rede $rede, AtualizarRedeData $data): Rede
    {
        $rede->update(['nome' => $data->nome]);

        return $rede->fresh();
    }

    public function alterarPlano(Rede $rede, Plano $plano): Rede
    {
        $rede->update(['plano_id' => $plano->id]);

        return $rede->fresh();
    }
}
