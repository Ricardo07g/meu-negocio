<?php

declare(strict_types=1);

namespace App\Modules\Arquivo\Services;

use App\Exceptions\NegocioException;
use App\Modules\Arquivo\Contracts\PossuiArquivos;
use App\Modules\Arquivo\Models\Arquivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Log, Storage};
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Camada de I/O de arquivos (imagens, PDFs, etc.) sobre o disco configurado
 * (Cloudflare R2 em producao). Gera miniatura quando o arquivo e imagem.
 *
 * Imagens convertiveis (jpeg/png/webp) sao normalizadas em WebP na gravacao —
 * original e miniatura. Demais tipos (PDF, GIF) sao guardados como enviados.
 *
 * Fluxos:
 *  - Dono ja existe (avatar, edicao de galeria): armazenar()/sincronizarUnico().
 *  - Dono ainda nao existe (criacao de Produto): armazenarRascunho() grava em
 *    {sistema}/tmp/{token}/ e anexarRascunhos() move para o path final ao salvar.
 */
class ArquivoService
{
    /**
     * Chave de sessao do token de staging de imagem unica (avatar).
     *
     * Deliberadamente distinta de `arquivo_rascunho_token` (galeria do Produto):
     * `anexarRascunhos()` faz `deleteDirectory(tmp/{token})` ao final, entao um
     * token compartilhado faria o salvamento de um produto apagar o avatar
     * estacionado em outra aba.
     */
    public const SESSAO_TOKEN_UNICO = 'arquivo_rascunho_unico';

    /**
     * Mimes que sabemos reencodar com seguranca em WebP. GIF fica de fora de
     * proposito: o GD achata a animacao no primeiro quadro.
     */
    private const MIMES_CONVERSIVEIS = ['image/jpeg', 'image/png', 'image/webp'];

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    private function disco(): string
    {
        return (string) config('arquivos.disco', 'r2');
    }

    // █████╗ ██████╗ ███╗   ███╗ █████╗ ███████╗███████╗███╗   ██╗ █████╗ ██████╗
    // ██╔══██╗██╔══██╗████╗ ████║██╔══██╗╚══███╔╝██╔════╝████╗  ██║██╔══██╗██╔══██╗
    // ███████║██████╔╝██╔████╔██║███████║  ███╔╝ █████╗  ██╔██╗ ██║███████║██████╔╝
    // ██╔══██║██╔══██╗██║╚██╔╝██║██╔══██║ ███╔╝  ██╔══╝  ██║╚██╗██║██╔══██║██╔══██╗
    // ██║  ██║██║  ██║██║ ╚═╝ ██║██║  ██║███████╗███████╗██║ ╚████║██║  ██║██║  ██║
    // ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═══╝╚═╝  ╚═╝╚═╝  ╚═╝

    /**
     * Grava um arquivo no path final do dono e cria o registro.
     */
    public function armazenar(Model&PossuiArquivos $dono, UploadedFile $arquivo, string $colecao = 'default'): Arquivo
    {
        $this->validar($dono, $arquivo, $colecao);

        $unica = (bool) ($dono->configColecao($colecao)['unica'] ?? false);

        if ($unica) {
            foreach ($dono->arquivos()->where('colecao', $colecao)->get() as $antigo) {
                /** @var Arquivo $antigo */
                $this->remover($antigo);
            }
        }

        $meta = $this->gravarArquivo($arquivo, $dono->diretorioBaseArquivos($colecao));
        $existentes = $dono->arquivos()->where('colecao', $colecao)->count();

        /** @var Arquivo $arq */
        $arq = $dono->arquivos()->create(array_merge($meta, [
            'rede_id' => $dono->getAttribute('rede_id'),
            'empresa_id' => $dono->empresaIdParaArquivo(),
            'colecao' => $colecao,
            'ordem' => $existentes,
            'principal' => $existentes === 0,
        ]));

        return $arq;
    }

    /**
     * Substitui (ou remove) o unico arquivo de uma colecao — usado por avatares.
     */
    public function sincronizarUnico(Model&PossuiArquivos $dono, string $colecao, ?UploadedFile $arquivo, bool $remover = false): void
    {
        if ($arquivo instanceof UploadedFile) {
            $this->armazenar($dono, $arquivo, $colecao);

            return;
        }

        if ($remover) {
            foreach ($dono->arquivos()->where('colecao', $colecao)->get() as $a) {
                /** @var Arquivo $a */
                $this->remover($a);
            }
        }
    }

    // ██████╗  █████╗ ███████╗ ██████╗██╗   ██╗███╗   ██╗██╗  ██╗ ██████╗
    // ██╔══██╗██╔══██╗██╔════╝██╔════╝██║   ██║████╗  ██║██║  ██║██╔═══██╗
    // ██████╔╝███████║███████╗██║     ██║   ██║██╔██╗ ██║███████║██║   ██║
    // ██╔══██╗██╔══██║╚════██║██║     ██║   ██║██║╚██╗██║██╔══██║██║   ██║
    // ██║  ██║██║  ██║███████║╚██████╗╚██████╔╝██║ ╚████║██║  ██║╚██████╔╝
    // ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝ ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝ ╚═════╝

    /**
     * Grava um upload na area de staging ({sistema}/tmp/{token}/) — usado
     * quando o dono ainda nao existe (criacao). Nada e persistido no banco.
     *
     * @return array{caminho: string, url: string, thumb_url: string|null, mime: string, nome_original: string}
     */
    public function armazenarRascunho(UploadedFile $arquivo, string $token): array
    {
        $meta = $this->gravarArquivo($arquivo, $this->diretorioTmp($token));
        $disco = Storage::disk($this->disco());

        return [
            'caminho' => $meta['caminho'],
            'url' => $disco->url($meta['caminho']),
            'thumb_url' => $meta['caminho_thumb'] ? $disco->url($meta['caminho_thumb']) : null,
            'mime' => $meta['mime'],
            'nome_original' => $meta['nome_original'],
        ];
    }

    /**
     * Remove um arquivo de staging (original + miniatura por convencao).
     */
    public function removerRascunho(string $token, string $caminho): void
    {
        if (! $this->caminhoPertenceAoToken($caminho, $token)) {
            return;
        }

        $disco = Storage::disk($this->disco());
        $disco->delete(array_values(array_filter([$caminho, $this->caminhoThumbPorConvencao($caminho)])));
    }

    /**
     * Move os rascunhos ordenados para o path final do dono e cria os
     * registros. Valida o prefixo do token (amarrado a sessao) e a existencia
     * no bucket; deriva os metadados do proprio objeto.
     *
     * @param  list<string>  $caminhosOrdenados
     */
    public function anexarRascunhos(Model&PossuiArquivos $dono, string $colecao, string $token, array $caminhosOrdenados): void
    {
        $disco = Storage::disk($this->disco());
        $dirFinal = $dono->diretorioBaseArquivos($colecao);
        $max = $dono->configColecao($colecao)['max'] ?? null;

        $ordem = $dono->arquivos()->where('colecao', $colecao)->count();

        foreach ($caminhosOrdenados as $caminhoTmp) {
            if ($max !== null && $ordem >= (int) $max) {
                break;
            }
            if (! $this->caminhoPertenceAoToken($caminhoTmp, $token) || ! $disco->exists($caminhoTmp)) {
                continue;
            }

            $this->moverRascunhoParaDono($dono, $colecao, $caminhoTmp, $ordem);
            $ordem++;
        }

        // Limpa quaisquer sobras (uploads abandonados) do token.
        $disco->deleteDirectory($this->diretorioTmp($token));
    }

    /**
     * Promove UM arquivo estacionado para uma colecao `unica` (avatar),
     * substituindo o anterior. Usado quando o form volta com erro de validacao e
     * o usuario salva sem reenviar a imagem.
     *
     * Ignora em silencio caminho que nao pertence ao token ou que ja sumiu: o
     * caminho vem de um input do cliente e nao pode virar 500. Nao apaga o
     * diretorio do token — a galeria pode ter rascunhos vivos ali.
     */
    public function anexarRascunhoUnico(Model&PossuiArquivos $dono, string $colecao, string $token, string $caminho): void
    {
        if (! $this->caminhoPertenceAoToken($caminho, $token) || ! Storage::disk($this->disco())->exists($caminho)) {
            return;
        }

        foreach ($dono->arquivos()->where('colecao', $colecao)->get() as $antigo) {
            /** @var Arquivo $antigo */
            $this->remover($antigo);
        }

        $this->moverRascunhoParaDono($dono, $colecao, $caminho, 0);
    }

    /**
     * URLs de um arquivo estacionado, para reidratar o preview do formulario.
     * Devolve null quando o caminho nao pertence ao token vigente ou nao existe
     * mais — o caminho chega por input do cliente, entao nao se confia nele.
     *
     * @return array{caminho: string, url: string, thumb_url: string}|null
     */
    public function urlsDoRascunho(string $token, ?string $caminho): ?array
    {
        $caminho = trim((string) $caminho);
        $disco = Storage::disk($this->disco());

        if ($caminho === '' || ! $this->caminhoPertenceAoToken($caminho, $token) || ! $disco->exists($caminho)) {
            return null;
        }

        $thumb = $this->caminhoThumbPorConvencao($caminho);

        return [
            'caminho' => $caminho,
            'url' => $disco->url($caminho),
            'thumb_url' => $disco->exists($thumb) ? $disco->url($thumb) : $disco->url($caminho),
        ];
    }

    // ██████╗ ███████╗███████╗████████╗ █████╗ ███╗   ██╗████████╗███████╗
    // ██╔══██╗██╔════╝██╔════╝╚══██╔══╝██╔══██╗████╗  ██║╚══██╔══╝██╔════╝
    // ██████╔╝█████╗  ███████╗   ██║   ███████║██╔██╗ ██║   ██║   █████╗
    // ██╔══██╗██╔══╝  ╚════██║   ██║   ██╔══██║██║╚██╗██║   ██║   ██╔══╝
    // ██║  ██║███████╗███████║   ██║   ██║  ██║██║ ╚████║   ██║   ███████╗
    // ╚═╝  ╚═╝╚══════╝╚══════╝   ╚═╝   ╚═╝  ╚═╝╚═╝  ╚═══╝   ╚═╝   ╚══════╝

    public function remover(Arquivo $arquivo): void
    {
        $tipo = $arquivo->anexavel_type;
        $id = $arquivo->anexavel_id;
        $colecao = $arquivo->colecao;

        $this->removerObjetos($arquivo);
        $arquivo->delete();

        $this->renumerar($tipo, $id, $colecao);
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function reordenar(Model&PossuiArquivos $dono, string $colecao, array $ids): void
    {
        $arquivos = $dono->arquivos()->where('colecao', $colecao)->get()->keyBy('id');

        $ordem = 0;
        foreach ($ids as $id) {
            /** @var Arquivo|null $arq */
            $arq = $arquivos->get((int) $id);
            if (! $arq) {
                continue;
            }
            $arq->update(['ordem' => $ordem, 'principal' => $ordem === 0]);
            $ordem++;
        }
    }

    public function definirPrincipal(Model&PossuiArquivos $dono, string $colecao, int $id): void
    {
        $ids = $dono->arquivos()->where('colecao', $colecao)->orderBy('ordem')->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($i) => (int) $i !== $id));
        array_unshift($ids, $id);

        $this->reordenar($dono, $colecao, $ids);
    }

    // ██╗███╗   ██╗████████╗███████╗██████╗ ███╗   ██╗ ██████╗ ███████╗
    // ██║████╗  ██║╚══██╔══╝██╔════╝██╔══██╗████╗  ██║██╔═══██╗██╔════╝
    // ██║██╔██╗ ██║   ██║   █████╗  ██████╔╝██╔██╗ ██║██║   ██║███████╗
    // ██║██║╚██╗██║   ██║   ██╔══╝  ██╔══██╗██║╚██╗██║██║   ██║╚════██║
    // ██║██║ ╚████║   ██║   ███████╗██║  ██║██║ ╚████║╚██████╔╝███████║
    // ╚═╝╚═╝  ╚═══╝   ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝  ╚═══╝ ╚═════╝ ╚══════╝

    /**
     * @return array{disco: string, caminho: string, caminho_thumb: string|null, nome_original: string, extensao: string, mime: string, tamanho: int, largura: int|null, altura: int|null, hash: string|null}
     */
    private function gravarArquivo(UploadedFile $arquivo, string $dir): array
    {
        $uuid = (string) Str::uuid();
        $mime = (string) $arquivo->getMimeType();

        return $this->deveConverterParaWebp($mime)
            ? $this->gravarImagemWebp($arquivo, $dir, $uuid)
            : $this->gravarComoEnviado($arquivo, $dir, $uuid, $mime);
    }

    /**
     * Reencoda a imagem em WebP: um objeto para exibicao (limitado a
     * largura_maxima) e a miniatura. Nada e gravado antes do decode dar certo,
     * entao um upload invalido nao deixa orfao no bucket.
     *
     * @return array{disco: string, caminho: string, caminho_thumb: string|null, nome_original: string, extensao: string, mime: string, tamanho: int, largura: int|null, altura: int|null, hash: string|null}
     */
    private function gravarImagemWebp(UploadedFile $arquivo, string $dir, string $uuid): array
    {
        try {
            $imagem = $this->imageManager->decode((string) $arquivo->getRealPath());
        } catch (Throwable $e) {
            throw new NegocioException('Nao foi possivel ler a imagem enviada. Verifique se o arquivo nao esta corrompido.');
        }

        $disco = Storage::disk($this->disco());
        $encoder = new WebpEncoder(quality: (int) config('arquivos.imagem.qualidade'));

        // O original tambem e reduzido: o que vai para o bucket e o que a tela
        // serve, sem carregar 12 MP de camera de celular.
        $imagem = $imagem->scaleDown(width: (int) config('arquivos.imagem.largura_maxima'));
        $largura = $imagem->width();
        $altura = $imagem->height();

        $conteudo = (string) $imagem->encode($encoder);
        $caminho = "{$dir}/{$uuid}.webp";
        $disco->put($caminho, $conteudo);

        // A miniatura sai da imagem ja reduzida — mais barato, mesmo resultado.
        $caminhoThumb = "{$dir}/{$uuid}".config('arquivos.thumb.sufixo').'.webp';
        $disco->put(
            $caminhoThumb,
            (string) $imagem->scaleDown(width: (int) config('arquivos.thumb.largura'))->encode($encoder),
        );

        return [
            'disco' => $this->disco(),
            'caminho' => $caminho,
            'caminho_thumb' => $caminhoThumb,
            'nome_original' => $arquivo->getClientOriginalName(),
            'extensao' => 'webp',
            'mime' => 'image/webp',
            // Tamanho e hash descrevem o objeto gravado, nao o upload original.
            'tamanho' => strlen($conteudo),
            'largura' => $largura,
            'altura' => $altura,
            'hash' => hash('sha256', $conteudo),
        ];
    }

    /**
     * Guarda o arquivo como veio (PDF, GIF, ou imagem quando a conversao esta
     * desligada), gerando miniatura no mesmo formato quando for imagem.
     *
     * @return array{disco: string, caminho: string, caminho_thumb: string|null, nome_original: string, extensao: string, mime: string, tamanho: int, largura: int|null, altura: int|null, hash: string|null}
     */
    private function gravarComoEnviado(UploadedFile $arquivo, string $dir, string $uuid, string $mime): array
    {
        $disco = Storage::disk($this->disco());
        $ext = strtolower($arquivo->getClientOriginalExtension() ?: ($arquivo->guessExtension() ?? 'bin'));

        $caminho = $disco->putFileAs($dir, $arquivo, "{$uuid}.{$ext}");
        if ($caminho === false) {
            throw new NegocioException('Falha ao gravar o arquivo.');
        }

        $largura = $altura = null;
        $caminhoThumb = null;

        if (str_starts_with($mime, 'image/')) {
            // A miniatura e um extra: formato que o GD desta instalacao nao
            // sabe ler (webp sem --with-webp, avif) nao pode derrubar o upload.
            // Sem thumb, o accessor thumb_url cai no proprio original.
            try {
                $imagem = $this->imageManager->decode((string) $arquivo->getRealPath());
                $largura = $imagem->width();
                $altura = $imagem->height();

                $caminhoThumb = "{$dir}/{$uuid}".config('arquivos.thumb.sufixo').".{$ext}";
                $conteudo = (string) $imagem->scaleDown(width: (int) config('arquivos.thumb.largura'))->encodeUsingFileExtension($ext);
                $disco->put($caminhoThumb, $conteudo);
            } catch (Throwable $e) {
                $caminhoThumb = null;
                Log::warning('Nao foi possivel gerar a miniatura do arquivo.', [
                    'caminho' => $caminho,
                    'mime' => $mime,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return [
            'disco' => $this->disco(),
            'caminho' => $caminho,
            'caminho_thumb' => $caminhoThumb,
            'nome_original' => $arquivo->getClientOriginalName(),
            'extensao' => $ext,
            'mime' => $mime,
            'tamanho' => (int) $arquivo->getSize(),
            'largura' => $largura,
            'altura' => $altura,
            'hash' => hash_file('sha256', (string) $arquivo->getRealPath()) ?: null,
        ];
    }

    /**
     * Converter exige um GD compilado com --with-webp; sem isso o upload cai no
     * caminho "como enviado" em vez de estourar (ver os Dockerfiles do projeto).
     */
    private function deveConverterParaWebp(string $mime): bool
    {
        return (bool) config('arquivos.imagem.converter_para_webp')
            && in_array($mime, self::MIMES_CONVERSIVEIS, true)
            && function_exists('imagewebp');
    }

    /**
     * Move um objeto do staging para o path final do dono e cria o registro.
     * Os metadados sao re-derivados do proprio objeto no bucket — o staging nao
     * guarda nada no banco e nao se confia em dado vindo do cliente.
     *
     * Quem chama ja validou o token e a existencia do caminho.
     */
    private function moverRascunhoParaDono(Model&PossuiArquivos $dono, string $colecao, string $caminhoTmp, int $ordem): void
    {
        $disco = Storage::disk($this->disco());
        $dirFinal = $dono->diretorioBaseArquivos($colecao);

        $novoCaminho = $dirFinal.'/'.basename($caminhoTmp);
        $disco->move($caminhoTmp, $novoCaminho);

        $novoThumb = null;
        $thumbTmp = $this->caminhoThumbPorConvencao($caminhoTmp);
        if ($disco->exists($thumbTmp)) {
            $novoThumb = $dirFinal.'/'.basename($thumbTmp);
            $disco->move($thumbTmp, $novoThumb);
        }

        $dono->arquivos()->create([
            'rede_id' => $dono->getAttribute('rede_id'),
            'empresa_id' => $dono->empresaIdParaArquivo(),
            'colecao' => $colecao,
            'disco' => $this->disco(),
            'caminho' => $novoCaminho,
            'caminho_thumb' => $novoThumb,
            'nome_original' => basename($novoCaminho),
            'extensao' => strtolower(pathinfo($novoCaminho, PATHINFO_EXTENSION)),
            'mime' => (string) ($disco->mimeType($novoCaminho) ?: 'application/octet-stream'),
            'tamanho' => (int) $disco->size($novoCaminho),
            'hash' => null,
            'ordem' => $ordem,
            'principal' => $ordem === 0,
        ]);
    }

    private function validar(Model&PossuiArquivos $dono, UploadedFile $arquivo, string $colecao): void
    {
        $config = $dono->configColecao($colecao);
        $mimes = $config['mimes'] ?? config('arquivos.mimes');
        $maxKb = (int) ($config['max_kb'] ?? config('arquivos.max_kb'));
        $ext = strtolower($arquivo->getClientOriginalExtension());

        if (is_array($mimes) && $mimes !== [] && ! in_array($ext, $mimes, true)) {
            throw new NegocioException("Tipo de arquivo nao permitido (.{$ext}).");
        }

        if ($maxKb > 0 && $arquivo->getSize() > $maxKb * 1024) {
            throw new NegocioException("Arquivo excede o tamanho maximo de {$maxKb} KB.");
        }

        $max = $config['max'] ?? null;
        if (! ($config['unica'] ?? false) && $max !== null) {
            if ($dono->arquivos()->where('colecao', $colecao)->count() >= (int) $max) {
                throw new NegocioException("Limite de {$max} arquivos nesta colecao atingido.");
            }
        }
    }

    private function renumerar(string $tipo, int $id, string $colecao): void
    {
        $restantes = Arquivo::query()
            ->where('anexavel_type', $tipo)
            ->where('anexavel_id', $id)
            ->where('colecao', $colecao)
            ->orderBy('ordem')
            ->get();

        foreach ($restantes as $i => $arq) {
            $arq->update(['ordem' => $i, 'principal' => $i === 0]);
        }
    }

    private function removerObjetos(Arquivo $arquivo): void
    {
        $alvos = array_values(array_filter([$arquivo->caminho, $arquivo->caminho_thumb]));
        if ($alvos !== []) {
            Storage::disk($arquivo->disco)->delete($alvos);
        }
    }

    private function diretorioTmp(string $token): string
    {
        return config('arquivos.pasta_sistema').'/'.config('arquivos.prefixo_tmp').'/'.$token;
    }

    private function caminhoPertenceAoToken(string $caminho, string $token): bool
    {
        return $token !== '' && str_starts_with($caminho, $this->diretorioTmp($token).'/');
    }

    private function caminhoThumbPorConvencao(string $caminho): string
    {
        $dir = pathinfo($caminho, PATHINFO_DIRNAME);
        $nome = pathinfo($caminho, PATHINFO_FILENAME);
        $ext = pathinfo($caminho, PATHINFO_EXTENSION);

        return "{$dir}/{$nome}".config('arquivos.thumb.sufixo').".{$ext}";
    }
}
