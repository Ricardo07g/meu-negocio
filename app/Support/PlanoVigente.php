<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Tenant\Models\{Empresa, Plano};

/**
 * Resolve a licenca (plano) da empresa em que o usuario esta operando agora.
 *
 * O plano e da EMPRESA, nao da rede — uma unidade pode estar no Gratis enquanto outra
 * da mesma rede esta no Pro. Toda checagem de feature flag ou limite passa por aqui.
 *
 * A cadeia de resolucao espelha `ContextoEmpresa` com o mesmo fallback ja usado em
 * Venda, Agenda, FormaPagamento e Conta: contexto vigente > empresa default do usuario.
 */
class PlanoVigente
{
    public static function empresaId(): ?int
    {
        $contexto = ContextoEmpresa::resolver();

        if ($contexto !== null) {
            return $contexto;
        }

        $default = auth()->user()?->empresa_id;

        return $default !== null ? (int) $default : null;
    }

    public static function empresa(): ?Empresa
    {
        $id = self::empresaId();

        return $id === null ? null : Empresa::with('plano')->find($id);
    }

    public static function resolver(): ?Plano
    {
        return self::empresa()?->plano;
    }
}
