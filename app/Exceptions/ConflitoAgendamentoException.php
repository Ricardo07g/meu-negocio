<?php

declare(strict_types=1);

namespace App\Exceptions;

class ConflitoAgendamentoException extends NegocioException
{
    public function __construct(string $mensagem = 'Conflito de horário: o profissional já possui agendamento neste período.')
    {
        parent::__construct($mensagem, 409);
    }
}
