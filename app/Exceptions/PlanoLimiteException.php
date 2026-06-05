<?php

declare(strict_types=1);

namespace App\Exceptions;

class PlanoLimiteException extends NegocioException
{
    public function __construct(string $recurso)
    {
        parent::__construct("Limite do plano atingido para: {$recurso}. Faça upgrade do seu plano.", 422);
    }
}
