<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Contratos;

use Illuminate\Http\Request;
use Ricardo07g\GuardaBot\Sinal;

/**
 * Uma fonte de indicios de automacao. Detectores sao baratos e locais: nada de
 * chamada de rede aqui (provedor externo entra como desafio, nao como sinal).
 */
interface Detector
{
    /**
     * @return list<Sinal> vazio quando nada suspeito foi encontrado
     */
    public function avaliar(Request $request): array;
}
