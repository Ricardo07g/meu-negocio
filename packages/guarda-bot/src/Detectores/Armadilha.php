<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Detectores;

use Illuminate\Contracts\Encryption\{DecryptException, StringEncrypter};
use Illuminate\Http\Request;
use Ricardo07g\GuardaBot\Contratos\Detector;
use Ricardo07g\GuardaBot\Sinal;

/**
 * Honeypot + tempo minimo de preenchimento, renderizados pelo componente
 * <x-guarda-bot::armadilha />.
 *
 * - Campo invisivel preenchido: robo que preenche tudo o que encontra.
 * - Envio mais rapido que o minimo: script que posta assim que a pagina chega.
 *   Peso abaixo do limiar de proposito: gerenciador de senha tambem e rapido.
 * - Campos ausentes: o POST nao veio do formulario renderizado.
 *
 * So avalia metodos que alteram estado; GET nunca carrega a armadilha.
 */
class Armadilha implements Detector
{
    public function __construct(private readonly StringEncrypter $encrypter) {}

    /**
     * Valor do campo de tempo: a hora da renderizacao, em ms, criptografada
     * (o robo nao consegue forjar um formulario "antigo").
     */
    public function carimbo(?int $renderizadoEmMs = null): string
    {
        return $this->encrypter->encryptString((string) ($renderizadoEmMs ?? now()->getTimestampMs()));
    }

    public function avaliar(Request $request): array
    {
        if ($request->isMethodSafe()) {
            return [];
        }

        $campo = (string) config('guarda-bot.armadilha.campo');
        $campoTempo = (string) config('guarda-bot.armadilha.campo_tempo');

        if (! $request->exists($campo) && ! $request->exists($campoTempo)) {
            return [new Sinal('armadilha_ausente')];
        }

        $sinais = [];

        if (filled($request->input($campo))) {
            $sinais[] = new Sinal('armadilha_preenchida');
        }

        $renderizadoEm = $this->ler($request->input($campoTempo));

        if ($renderizadoEm === null) {
            $sinais[] = new Sinal('armadilha_invalida');
        } elseif (now()->getTimestampMs() - $renderizadoEm < (int) config('guarda-bot.armadilha.tempo_minimo_ms', 1500)) {
            $sinais[] = new Sinal('envio_rapido');
        }

        return $sinais;
    }

    private function ler(mixed $carimbo): ?int
    {
        if (! is_string($carimbo) || $carimbo === '') {
            return null;
        }

        try {
            $valor = $this->encrypter->decryptString($carimbo);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($valor) ? (int) $valor : null;
    }
}
