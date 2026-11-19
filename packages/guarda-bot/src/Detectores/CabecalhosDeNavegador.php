<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Detectores;

use Illuminate\Http\Request;
use Ricardo07g\GuardaBot\Contratos\Detector;
use Ricardo07g\GuardaBot\Sinal;

/**
 * Coerencia entre o que a requisicao diz ser (um navegador) e os cabecalhos
 * que todo navegador moderno envia.
 *
 * Script que copia o UA do Chrome costuma esquecer o resto. Cada falta pesa
 * pouco de proposito: navegador antigo ou incomum pode ter uma delas, e so a
 * soma com outros sinais deve bloquear.
 *
 * O que NAO se exige: `Sec-CH-UA` (Safari e Firefox nao enviam client hints).
 */
class CabecalhosDeNavegador implements Detector
{
    public function avaliar(Request $request): array
    {
        // Cliente que nem finge ser navegador e assunto do AgenteDeUsuario.
        if (! str_contains((string) $request->userAgent(), 'Mozilla/')) {
            return [];
        }

        $sinais = [];

        if (blank($request->header('Accept-Language'))) {
            $sinais[] = new Sinal('sem_accept_language');
        }

        // Navegadores so enviam Sec-Fetch-* para origem segura (HTTPS/localhost):
        // em HTTP comum a falta e esperada e nao diz nada.
        if ($request->isSecure()
            && ! $request->headers->has('Sec-Fetch-Site')
            && ! $request->headers->has('Sec-Fetch-Mode')) {
            $sinais[] = new Sinal('sem_sec_fetch');
        }

        if (stripos((string) $request->header('Sec-CH-UA'), 'HeadlessChrome') !== false) {
            $sinais[] = new Sinal('client_hint_headless');
        }

        return $sinais;
    }
}
