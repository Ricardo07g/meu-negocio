<?php

namespace App\Modules\Usuario\Policies;

use App\Modules\Usuario\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('usuario.ver');
    }

    public function view(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->podeAcessarEmpresa($alvo->empresa_id)
            && $usuario->can('usuario.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('usuario.criar');
    }

    public function update(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->podeAcessarEmpresa($alvo->empresa_id)
            && $usuario->can('usuario.editar');
    }

    public function delete(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->can('usuario.excluir');
    }
}
