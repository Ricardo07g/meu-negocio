<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('usuario.ver');
    }

    public function view(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $alvo->empresa_id)
            && $usuario->can('usuario.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('usuario.criar');
    }

    public function update(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && ($usuario->hasRole('Admin') || $usuario->empresa_id === $alvo->empresa_id)
            && $usuario->can('usuario.editar');
    }

    public function delete(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->can('usuario.excluir');
    }
}
