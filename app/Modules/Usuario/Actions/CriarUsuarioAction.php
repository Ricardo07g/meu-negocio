<?php

namespace App\Modules\Usuario\Actions;

use App\Modules\Tenant\Actions\ValidarPlanoAction;
use App\Modules\Usuario\DTOs\CriarUsuarioData;
use App\Modules\Servico\Models\Profissional;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;

class CriarUsuarioAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
    ) {}

    public function executar(Rede $rede, CriarUsuarioData $data): Usuario
    {
        $this->validarPlano->executar($rede, 'usuario');

        $usuario = Usuario::create([
            'rede_id' => $rede->id,
            'empresa_id' => $data->empresa_id,
            'nome' => $data->nome,
            'email' => $data->email,
            'password' => $data->password,
            'ativo' => true,
        ]);

        $usuario->assignRole($data->papel);

        if ($data->papel === 'Profissional') {
            Profissional::create([
                'rede_id' => $rede->id,
                'empresa_id' => $data->empresa_id,
                'usuario_id' => $usuario->id,
            ]);
        }

        return $usuario;
    }
}
