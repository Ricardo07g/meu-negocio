<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA por app autenticador (TOTP, RFC 6238 — ADR-0022).
 *
 * Ciclo: `iniciar` grava um segredo PENDENTE -> o usuario escaneia o QR ->
 * `confirmar` com um codigo do app ativa e devolve os codigos de recuperacao,
 * mostrados uma unica vez. Ate a confirmacao o login segue com um fator so.
 *
 * Os codigos de recuperacao sao guardados como sha256 (alta entropia, sem
 * necessidade de bcrypt) dentro do cast `encrypted:array`.
 */
class DoisFatoresService
{
    public const QTD_CODIGOS_RECUPERACAO = 8;

    /** Passos de 30s aceitos para cada lado — tolera relogio do celular levemente fora. */
    private const JANELA = 1;

    public function __construct(
        private Google2FA $google2fa,
    ) {}

    public function iniciar(Usuario $usuario): void
    {
        if ($usuario->temDoisFatoresAtivo()) {
            throw new NegocioException('A verificação em duas etapas já está ativa.');
        }

        $usuario->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_ultimo_passo' => null,
        ])->save();
    }

    /**
     * @return list<string> codigos de recuperacao em texto puro (mostrar uma vez)
     */
    public function confirmar(Usuario $usuario, string $codigo): array
    {
        if ($usuario->temDoisFatoresAtivo()) {
            throw new NegocioException('A verificação em duas etapas já está ativa.');
        }

        if ($usuario->two_factor_secret === null) {
            throw new NegocioException('Comece a configuração antes de confirmar o código.');
        }

        if (! $this->verificarCodigo($usuario, $codigo)) {
            throw new NegocioException('Código inválido. Confira o horário do celular e tente o código atual do app.');
        }

        $codigos = $this->novosCodigos();

        $usuario->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map($this->hash(...), $codigos),
        ])->save();

        $this->registrar($usuario, 'Verificação em duas etapas ativada');

        return $codigos;
    }

    /**
     * Valida um codigo do app e consome o passo de tempo dele: o mesmo codigo
     * (ou um anterior) nao passa de novo, mesmo dentro dos 30s.
     */
    public function verificarCodigo(Usuario $usuario, string $codigo): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        if (strlen($codigo) !== 6) {
            return false;
        }

        return $this->comUsuarioTravado($usuario, function (Usuario $atual) use ($codigo): bool {
            if ($atual->two_factor_secret === null) {
                return false;
            }

            $passo = $this->google2fa->verifyKeyNewer(
                $atual->two_factor_secret,
                $codigo,
                $atual->two_factor_ultimo_passo ?? 0,
                self::JANELA,
            );

            if ($passo === false || $passo === true) {
                return false;
            }

            $atual->forceFill(['two_factor_ultimo_passo' => $passo])->save();

            return true;
        });
    }

    /** Cada codigo de recuperacao vale uma unica vez. */
    public function usarCodigoRecuperacao(Usuario $usuario, string $codigo): bool
    {
        $alvo = $this->hash($codigo);

        $usado = $this->comUsuarioTravado($usuario, function (Usuario $atual) use ($alvo): bool {
            $hashes = $atual->two_factor_recovery_codes ?? [];

            foreach ($hashes as $indice => $hash) {
                if (hash_equals($hash, $alvo)) {
                    unset($hashes[$indice]);
                    $atual->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                    return true;
                }
            }

            return false;
        });

        if ($usado) {
            $this->registrar($usuario, 'Código de recuperação usado no login');
        }

        return $usado;
    }

    /**
     * Le e grava o estado do 2FA com a linha travada. Duas requisicoes simultaneas
     * carregam o usuario antes de qualquer uma gravar; sem reler sob lock, a
     * segunda decidiria com o estado velho e aceitaria o mesmo codigo.
     *
     * @param  \Closure(Usuario): bool  $acao
     */
    private function comUsuarioTravado(Usuario $usuario, \Closure $acao): bool
    {
        return DB::transaction(function () use ($usuario, $acao): bool {
            $atual = Usuario::query()->lockForUpdate()->findOrFail($usuario->getKey());
            $resultado = $acao($atual);

            // Devolve o estado gravado a instancia de quem chamou.
            $usuario->setRawAttributes($atual->getAttributes(), true);

            return $resultado;
        });
    }

    /**
     * @return list<string>
     */
    public function regenerarCodigos(Usuario $usuario): array
    {
        if (! $usuario->temDoisFatoresAtivo()) {
            throw new NegocioException('Ative a verificação em duas etapas antes de gerar códigos.');
        }

        $codigos = $this->novosCodigos();
        $usuario->forceFill(['two_factor_recovery_codes' => array_map($this->hash(...), $codigos)])->save();

        $this->registrar($usuario, 'Códigos de recuperação regenerados');

        return $codigos;
    }

    /** Desligar pelo proprio usuario: proibido quando a rede exige o 2FA. */
    public function desativar(Usuario $usuario): void
    {
        if ($usuario->rede?->exigir_2fa) {
            throw new NegocioException('Sua rede exige a verificação em duas etapas: ela não pode ser desativada.');
        }

        $this->limpar($usuario);
        $this->registrar($usuario, 'Verificação em duas etapas desativada');
    }

    /**
     * Zera o 2FA de quem perdeu o celular. Vale mesmo com a rede exigindo: no
     * proximo acesso o usuario e levado a configurar de novo. O Admin faz isso pela
     * tela para quem NAO e Admin (UsuarioPolicy); o 2FA de um Admin so o suporte
     * redefine, pelo comando `usuarios:redefinir-2fa`, com motivo na auditoria.
     */
    public function redefinir(Usuario $alvo, string $por = 'pelo administrador', ?string $motivo = null): void
    {
        $this->limpar($alvo);
        $this->registrar($alvo, "Verificação em duas etapas redefinida {$por}", array_filter(['motivo' => $motivo]));
    }

    /** Politica da rede, ligada/desligada pelo Admin (autorizado no controller). */
    public function definirExigencia(Rede $rede, bool $exigir): void
    {
        if ($rede->exigir_2fa === $exigir) {
            return;
        }

        // Fora do $fillable da Rede de proposito: politica de seguranca nao entra por mass-assignment.
        $rede->forceFill(['exigir_2fa' => $exigir])->save();

        activity()
            ->performedOn($rede)
            ->causedBy(auth()->user())
            ->log($exigir ? 'Rede passou a exigir verificação em duas etapas' : 'Rede deixou de exigir verificação em duas etapas');
    }

    public function qrCodeSvg(Usuario $usuario): string
    {
        $url = $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $usuario->email,
            (string) $usuario->two_factor_secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($url);
    }

    private function limpar(Usuario $usuario): void
    {
        $usuario->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_ultimo_passo' => null,
        ])->save();
    }

    /**
     * @return list<string> no formato XXXXX-XXXXX, sem caracteres ambiguos (0/O, 1/I)
     */
    private function novosCodigos(): array
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return array_map(function () use ($alfabeto) {
            $bruto = '';
            for ($i = 0; $i < 10; $i++) {
                $bruto .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            return substr($bruto, 0, 5).'-'.substr($bruto, 5);
        }, range(1, self::QTD_CODIGOS_RECUPERACAO));
    }

    /** Normaliza (maiusculas, sem hifen/espaco) antes do hash: digitar "abcde fghij" vale. */
    private function hash(string $codigo): string
    {
        return hash('sha256', Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? ''));
    }

    /**
     * @param  array<string, string>  $propriedades
     */
    private function registrar(Usuario $usuario, string $descricao, array $propriedades = []): void
    {
        activity()
            ->performedOn($usuario)
            ->causedBy(auth()->user() ?? $usuario)
            ->withProperties($propriedades)
            ->log($descricao);
    }
}
