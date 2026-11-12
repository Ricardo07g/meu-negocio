<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * Monta o Content-Security-Policy da requisicao (ADR-0023).
 *
 * Script: so da propria origem, dos servicos listados em `config/seguranca.php` e
 * os blocos inline que carregam o nonce desta requisicao (`<script @nonce>`) — e o
 * que impede um script injetado (XSS) de executar. Sem `'unsafe-inline'` e sem
 * `'unsafe-eval'` em script-src.
 *
 * Estilo mantem `'unsafe-inline'`: ha ~110 atributos `style=` nas telas, o
 * SweetAlert e o ApexCharts injetam CSS em runtime, e CSS injetado nao executa
 * codigo. Nao por nonce em style-src: com nonce, o navegador ignora o
 * `'unsafe-inline'` e tudo isso quebra.
 */
final class PoliticaCsp
{
    public const MODO_ATIVO = 'ativo';

    public const MODO_RELATORIO = 'relatorio';

    public const MODO_DESLIGADO = 'desligado';

    public static function modo(): string
    {
        $modo = (string) config('seguranca.csp.modo', self::MODO_ATIVO);

        // Valor desconhecido (erro de digitacao na variavel) cai no modo seguro.
        return in_array($modo, [self::MODO_RELATORIO, self::MODO_DESLIGADO], true) ? $modo : self::MODO_ATIVO;
    }

    public static function cabecalho(): string
    {
        return self::modo() === self::MODO_RELATORIO
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }

    public static function montar(string $nonce): string
    {
        $vite = self::origensDoViteEmDev();

        $diretivas = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...self::extras('script-src'), ...$vite['http']],
            'style-src' => ["'self'", "'unsafe-inline'", ...self::extras('style-src'), ...$vite['http']],
            'font-src' => ["'self'", ...self::extras('font-src'), ...$vite['http']],
            // data: = SVGs embutidos no CSS do Bootstrap/calendario; blob: = preview do upload.
            'img-src' => ["'self'", 'data:', 'blob:', ...self::origensDeArquivos(), ...self::extras('img-src'), ...$vite['http']],
            // blob: = o recorte de imagem le o EXIF da foto escolhida por XHR na URL blob.
            'connect-src' => ["'self'", 'blob:', ...self::extras('connect-src'), ...$vite['http'], ...$vite['ws']],
            'frame-src' => ["'self'", ...self::extras('frame-src')],
            // 'self' e nao 'none': o modal de recibo embute o PDF da propria app num iframe.
            'frame-ancestors' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];

        $partes = [];

        foreach ($diretivas as $diretiva => $valores) {
            $partes[] = $diretiva.' '.implode(' ', array_values(array_unique($valores)));
        }

        return implode('; ', $partes);
    }

    /** @return list<string> */
    private static function extras(string $diretiva): array
    {
        return array_values(array_filter(
            (array) config("seguranca.csp.origens.{$diretiva}", []),
            fn (mixed $origem): bool => is_string($origem) && $origem !== '',
        ));
    }

    /**
     * Origem publica de onde as imagens enviadas sao servidas: o dominio do R2
     * (`R2_PUBLIC_BASE_URL`) e, em dev, o `/storage` do disco public. So a origem
     * (esquema + host + porta) — CSP nao precisa do caminho.
     *
     * @return list<string>
     */
    private static function origensDeArquivos(): array
    {
        $discos = array_unique(['r2', 'public', (string) config('arquivos.disco')]);
        $origens = [];

        foreach ($discos as $disco) {
            $origem = self::origemDe(config("filesystems.disks.{$disco}.url"));

            if ($origem !== null) {
                $origens[] = $origem;
            }
        }

        return array_values(array_unique($origens));
    }

    /**
     * Com `npm run dev` rodando, os assets vem do servidor do Vite (outra porta) e o
     * HMR conversa por WebSocket. So existe em dev: o arquivo `public/hot` nao vai
     * para producao.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private static function origensDoViteEmDev(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $origem = self::origemDe(trim((string) @file_get_contents(Vite::hotFile())));

        if ($origem === null) {
            return ['http' => [], 'ws' => []];
        }

        return ['http' => [$origem], 'ws' => [(string) preg_replace('/^http/', 'ws', $origem)]];
    }

    private static function origemDe(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $partes = parse_url($url);

        if (! is_array($partes) || ! isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $porta = isset($partes['port']) ? ':'.$partes['port'] : '';

        return strtolower($partes['scheme']).'://'.$partes['host'].$porta;
    }
}
