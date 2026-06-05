<?php

declare(strict_types=1);

namespace App\Exceptions;

class NegocioException extends \RuntimeException
{
    public function __construct(
        string $mensagem,
        int $codigo = 422,
        ?\Throwable $anterior = null,
    ) {
        parent::__construct($mensagem, $codigo, $anterior);
    }
}
