<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot;

/**
 * Como o middleware `guarda-bot` age sobre o veredito.
 */
enum Modo: string
{
    case Desligado = 'desligado';
    case Observar = 'observar';
    case Ativo = 'ativo';

    /**
     * Le o modo da config. Valor desconhecido vira `observar`: um erro de
     * digitacao na variavel de ambiente nao pode trancar usuarios reais.
     */
    public static function atual(): self
    {
        return self::tryFrom((string) config('guarda-bot.modo')) ?? self::Observar;
    }
}
