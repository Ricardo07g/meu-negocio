<?php

declare(strict_types=1);

namespace App\Modules\Ia\Contracts;

use App\Modules\Ia\DTOs\{PedidoIa, RespostaIa};
use App\Modules\Ia\Exceptions\IaIndisponivelException;

/**
 * Contrato do provedor de IA. Nao sabe o que e cliente, carteira ou NPS:
 * recebe instrucoes + dados ja mastigados + schema de saida, devolve dado estruturado.
 *
 * E o unico ponto do sistema que fala com um provedor externo de IA — trocar de
 * modelo e trocar a implementacao registrada em `config('ia.driver')`.
 */
interface Ia
{
    /** Ha credencial configurada? Falso => a feature esta desligada, ninguem chama nada. */
    public function estaAtivo(): bool;

    /** Identificador do modelo em uso (gravado junto da analise, para auditoria). */
    public function modelo(): string;

    /**
     * @throws IaIndisponivelException falha de rede, timeout,
     *                                 erro do provedor ou resposta fora do schema.
     */
    public function analisar(PedidoIa $pedido): RespostaIa;
}
