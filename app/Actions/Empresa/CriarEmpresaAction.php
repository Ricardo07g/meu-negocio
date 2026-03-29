<?php

namespace App\Actions\Empresa;

use App\Actions\Plano\ValidarPlanoAction;
use App\DTO\Empresa\CriarEmpresaData;
use App\Models\Empresa;
use App\Models\Rede;

class CriarEmpresaAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
    ) {}

    public function executar(Rede $rede, CriarEmpresaData $data): Empresa
    {
        $this->validarPlano->executar($rede, 'empresa');

        return Empresa::create([
            'rede_id' => $rede->id,
            'nome' => $data->nome,
            'documento' => $data->documento,
            'telefone' => $data->telefone,
            'email' => $data->email,
        ]);
    }
}
