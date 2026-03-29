<?php

namespace App\Services;

use App\Actions\Empresa\CriarEmpresaAction;
use App\Actions\Usuario\CriarUsuarioAction;
use App\DTO\Rede\AtualizarRedeData;
use App\DTO\Rede\CriarRedeData;
use App\DTO\Empresa\CriarEmpresaData;
use App\DTO\Usuario\CriarUsuarioData;
use App\Enums\StatusRede;
use App\Models\Plano;
use App\Models\Rede;
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
                new CriarEmpresaData(nome: $data->nome)
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
