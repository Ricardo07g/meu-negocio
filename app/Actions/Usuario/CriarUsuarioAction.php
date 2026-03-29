<?php

namespace App\Actions\Usuario;

use App\Actions\Plano\ValidarPlanoAction;
use App\DTO\Usuario\CriarUsuarioData;
use App\Models\Profissional;
use App\Models\Rede;
use App\Models\Usuario;

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
