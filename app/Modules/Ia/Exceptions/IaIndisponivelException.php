<?php

declare(strict_types=1);

namespace App\Modules\Ia\Exceptions;

use App\Exceptions\NegocioException;

/**
 * O provedor de IA nao respondeu, respondeu erro, ou respondeu fora do schema.
 *
 * Sempre tratavel: a analise e um enriquecimento, nunca o caminho critico — a tela
 * que a consome precisa continuar util sem ela.
 */
class IaIndisponivelException extends NegocioException
{
    public function __construct(string $motivo, ?\Throwable $anterior = null)
    {
        parent::__construct("Analise por IA indisponivel: {$motivo}", 422, $anterior);
    }
}
