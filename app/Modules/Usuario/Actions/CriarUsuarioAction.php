<?php

namespace App\Modules\Usuario\Actions;

use App\Modules\Tenant\Actions\ValidarPlanoAction;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Usuario\Models\Usuario;

class CriarUsuarioAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
    ) {}

    public function executar(Rede $rede, UsuarioData $data): Usuario
    {
        $this->validarPlano->executar($rede, 'usuario');

        $papel = $data->papel;

        $usuario = Usuario::create([
            'rede_id' => $rede->id,
            'empresa_id' => $data->empresa_id,
            'nome' => $data->nome,
            'email' => $data->email,
            'password' => $data->password,
            'ativo' => true,
            'atende' => $data->atende ?? ($papel === 'Admin'),
        ]);

        $usuario->assignRole($papel);

        return $usuario;
    }
}
