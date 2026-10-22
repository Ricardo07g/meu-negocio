<?php

declare(strict_types=1);

namespace App\Modules\Auth\Support;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Contracts\Session\Session;

/**
 * Pendencia do login em duas etapas (ADR-0022): a senha ja foi conferida, mas a
 * sessao ainda NAO esta autenticada. Vive so na sessao, com prazo e com teto
 * de erros — o throttle de rota e por IP, e trocar de IP renovaria as tentativas;
 * este teto e da pendencia, entao estourou, volta ao login (e ao Turnstile).
 */
class PendenciaDoisFatores
{
    private const CHAVE = 'login.dois_fatores';

    public const MINUTOS_PARA_RESPONDER = 5;

    public const MAXIMO_DE_ERROS = 5;

    public function __construct(
        private Session $sessao,
    ) {}

    public function registrar(Usuario $usuario): void
    {
        $this->sessao->put(self::CHAVE, [
            'id' => $usuario->getKey(),
            'expira' => now()->addMinutes(self::MINUTOS_PARA_RESPONDER)->getTimestamp(),
            'erros' => 0,
        ]);
    }

    /**
     * Usuario da pendencia valida: dentro do prazo, ainda ativo e com 2FA ainda
     * ligado (o Admin pode ter redefinido no meio do caminho).
     */
    public function usuario(): ?Usuario
    {
        $pendencia = $this->sessao->get(self::CHAVE);

        if (! is_array($pendencia) || ($pendencia['expira'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $usuario = Usuario::find($pendencia['id'] ?? null);

        return ($usuario !== null && $usuario->ativo && $usuario->temDoisFatoresAtivo()) ? $usuario : null;
    }

    /** Conta um erro; devolve false quando o teto estourou e a pendencia caiu. */
    public function registrarErro(): bool
    {
        $pendencia = (array) $this->sessao->get(self::CHAVE, []);
        $erros = (int) ($pendencia['erros'] ?? 0) + 1;

        if ($erros >= self::MAXIMO_DE_ERROS) {
            $this->descartar();

            return false;
        }

        $this->sessao->put(self::CHAVE.'.erros', $erros);

        return true;
    }

    public function descartar(): void
    {
        $this->sessao->forget(self::CHAVE);
    }
}
