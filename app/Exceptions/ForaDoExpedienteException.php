<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * O horário pedido está fora do expediente da unidade.
 *
 * Não é um erro terminal: é uma pergunta. Quem tem a permissão
 * `agendamento.forcar_horario` pode repetir a requisição com
 * `forcar_horario=true` e o agendamento nasce marcado como encaixe. Por isso a
 * exceção carrega um código estável — a tela distingue "não pode" de "quer
 * mesmo?" pelo código, não pelo texto da mensagem.
 */
class ForaDoExpedienteException extends NegocioException
{
    public const CODIGO = 'fora_expediente';
}
