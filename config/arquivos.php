<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de armazenamento
    |--------------------------------------------------------------------------
    |
    | Disco (config/filesystems.php) onde os arquivos sao gravados. Em producao
    | e o Cloudflare R2; nos testes e sobrescrito por Storage::fake('r2').
    |
    */

    'disco' => env('ARQUIVOS_DISCO', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Pasta raiz do sistema (bucket compartilhado)
    |--------------------------------------------------------------------------
    |
    | O bucket e compartilhado entre sistemas; tudo deste sistema vive sob esta
    | pasta. Estrutura final:
    |   {pasta_sistema}/redes/{rede_id}/[empresas/{empresa_id}/]{tabela}/{id}/{colecao}/{uuid}.{ext}
    |
    */

    'pasta_sistema' => env('ARQUIVOS_PASTA_SISTEMA', Str::slug((string) env('APP_NAME', 'meu-negocio'))),

    /*
    |--------------------------------------------------------------------------
    | Area de rascunho (staging)
    |--------------------------------------------------------------------------
    |
    | Uploads de formularios de criacao (quando o dono ainda nao existe) vao
    | para {pasta_sistema}/{prefixo_tmp}/{token}/. Ao salvar, sao movidos para
    | o path final. Configure uma regra de lifecycle no R2 sobre este prefixo
    | para expirar rascunhos abandonados; o comando arquivos:limpar-rascunhos
    | e um reforco.
    |
    */

    'prefixo_tmp' => env('ARQUIVOS_PREFIXO_TMP', 'tmp'),
    'tmp_ttl_horas' => (int) env('ARQUIVOS_TMP_TTL_HORAS', 48),

    /*
    |--------------------------------------------------------------------------
    | Miniaturas (apenas imagens)
    |--------------------------------------------------------------------------
    |
    | Imagens ganham uma miniatura (_thumb) redimensionada para uso em
    | listagens/avatares. Nao-imagens (PDF, etc.) sao guardadas sem thumb.
    |
    */

    'thumb' => [
        'largura' => (int) env('ARQUIVOS_THUMB_LARGURA', 300),
        'sufixo' => '_thumb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites globais (fallback)
    |--------------------------------------------------------------------------
    |
    | Cada model pode restringir por colecao em colecoesArquivo(); estes sao os
    | limites globais aplicados quando a colecao nao especifica.
    |
    */

    'max_kb' => (int) env('ARQUIVOS_MAX_KB', 8192),
    'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'],

];
