<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\{Empresa, Rede};

class CriarEmpresaAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
    ) {}

    public function executar(Rede $rede, EmpresaData $data): Empresa
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
