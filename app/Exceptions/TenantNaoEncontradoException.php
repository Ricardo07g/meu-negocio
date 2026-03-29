<?php

namespace App\Exceptions;

class TenantNaoEncontradoException extends NegocioException
{
    public function __construct()
    {
        parent::__construct('Conta não encontrada ou sem permissão de acesso.', 403);
    }
}
