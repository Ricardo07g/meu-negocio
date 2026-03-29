<?php

namespace App\Exceptions;

class EmpresaNaoEncontradaException extends NegocioException
{
    public function __construct()
    {
        parent::__construct('Empresa não encontrada ou sem permissão de acesso.', 403);
    }
}
