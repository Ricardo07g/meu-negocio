<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use PragmaRX\Google2FA\Google2FA;

/**
 * Helpers de 2FA (ADR-0022) para os testes: ativar de verdade pelo Service e
 * gerar o codigo que o app autenticador mostraria agora.
 */
trait DoisFatores
{
    /**
     * Ativa o 2FA pelo caminho real (iniciar + confirmar). A confirmacao queima o
     * passo de 30s atual (anti-replay); aqui ele e liberado de novo para o teste
     * poder logar logo em seguida. O `travel()` nao serve: o google2fa le
     * `time()` direto, nao o relogio do Carbon.
     *
     * @return list<string> codigos de recuperacao em texto puro
     */
    protected function ativarDoisFatores(Usuario $usuario): array
    {
        $service = app(DoisFatoresService::class);
        $service->iniciar($usuario);
        $codigos = $service->confirmar($usuario->refresh(), $this->codigoAtual($usuario));

        $usuario->forceFill(['two_factor_ultimo_passo' => null])->save();

        return $codigos;
    }

    protected function codigoAtual(Usuario $usuario): string
    {
        return app(Google2FA::class)->getCurrentOtp((string) $usuario->refresh()->two_factor_secret);
    }
}
