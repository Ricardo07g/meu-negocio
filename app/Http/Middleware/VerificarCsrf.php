<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * A protecao CSRF padrao do Laravel, sem o cookie `XSRF-TOKEN`.
 *
 * Esse cookie existe para o axios ler o token e devolve-lo no header
 * `X-XSRF-TOKEN` — por isso nao pode ser HttpOnly. Aqui ninguem o usa: os forms
 * levam `_token` e o JS manda `X-CSRF-TOKEN` lido do `<meta name="csrf-token">`.
 * Um cookie legivel por script que nada consome so aumenta a superficie.
 *
 * A verificacao continua identica (`_token`, `X-CSRF-TOKEN` e `X-XSRF-TOKEN`).
 */
class VerificarCsrf extends PreventRequestForgery
{
    /** @var bool */
    protected $addHttpCookie = false;
}
