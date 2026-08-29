<?php

declare(strict_types=1);

namespace App\Modules\Ia\Exceptions;

use App\Exceptions\NegocioException;

/**
 * Nao ha base suficiente para a analise valer alguma coisa.
 *
 * Existe para **nao gastar cota a toa**: pedir a um modelo que interprete a carteira de
 * tres clientes produz texto generico e cobra igual. Melhor dizer que ainda faltam dados.
 */
class DadosInsuficientesException extends NegocioException
{
    public function __construct(string $mensagem)
    {
        parent::__construct($mensagem, 422);
    }
}
