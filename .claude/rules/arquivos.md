---
paths:
  - "app/Modules/Arquivo/**"
  - "app/Traits/TemArquivos.php"
  - "config/arquivos.php"
  - "config/filesystems.php"
  - "resources/js/produto-imagens.js"
  - "resources/views/components/thumb.blade.php"
  - "resources/views/components/campo-imagem.blade.php"
---

# Modulo: Arquivo (uploads genericos — imagens, PDFs, etc.)

Base generica para anexar arquivos a qualquer model, via tabela polimorfica `arquivos` agrupada por
`colecao`. Imagem e so "arquivo com mime `image/*`" (ganha miniatura). Storage no Cloudflare R2.
-> decisao completa em `docs/ADR/0008-armazenamento-de-arquivos-r2.md` e
`docs/ADR/0015-normalizacao-de-imagens-em-webp.md`.

## Entidades & camadas
- **Model `Arquivo`** (`app/Modules/Arquivo/Models/Arquivo.php`): `BaseModel` (escopo so `rede_id`,
  **nao** usa EmpresaTrait). `morphTo anexavel`. Accessors `url`/`thumb_url` (via
  `Storage::disk($disco)->url()`), helper `ehImagem()`. `$appends = ['url','thumb_url']`.
- **Trait `App\Traits\TemArquivos`** + contrato `App\Modules\Arquivo\Contracts\PossuiArquivos`: o
  model `implements PossuiArquivos` e `use TemArquivos`. Fornece `arquivos()` (morphMany, ordenado),
  `arquivoPrincipal()` (morphOne where principal=true, **eager-loadavel** p/ evitar N+1 em listagens),
  `arquivosDaColecao()`, accessors `imagem_url`/`imagem_thumb_url`, e `diretorioBaseArquivos()`.
  O model declara coleções em `colecoesArquivo()`.
- **`ArquivoService`**: `armazenar`, `sincronizarUnico` (coleção `unica`), `armazenarRascunho` /
  `removerRascunho` / `anexarRascunhos` / `anexarRascunhoUnico` / `urlsDoRascunho` (staging),
  `remover`, `reordenar`, `definirPrincipal`.
  Toda a I/O passa por `gravarArquivo()`, que bifurca em `gravarImagemWebp()` (imagem convertivel)
  ou `gravarComoEnviado()` (PDF/GIF/conversao desligada). `intervention/image` com driver GD.
- **`ProdutoArquivoController`** (modulo Produto): endpoints AJAX da galeria (store/destroy/reordenar/
  principal + rascunho store/destroy), autorizados via `ProdutoPolicy`.

## Coleções declaradas hoje
- Produto -> `galeria` (multipla, max 8, gera thumb).
- Cliente / Servico / Usuario -> `avatar` (`unica => true`, gera thumb).
- Coleção nao declarada usa limites globais de `config/arquivos.php` (mimes incluem pdf).

## Normalizacao em WebP (ADR-0015)
Imagem `image/jpeg|png|webp` e **reencodada em WebP na gravacao** — original (limitado a
`arquivos.imagem.largura_maxima`, 1600px) e miniatura. GIF fica de fora (o GD achataria a animacao)
e PDF segue o caminho "como enviado". Config em `config/arquivos.php` -> `imagem`
(`ARQUIVOS_CONVERTER_WEBP`, `ARQUIVOS_WEBP_QUALIDADE`, `ARQUIVOS_LARGURA_MAXIMA`).
- `extensao`/`mime` gravados sao sempre `webp`/`image/webp`; `tamanho` e `hash` descrevem **o objeto
  no bucket**, nao o upload (o `hash_file` do original nao corresponderia a nada la).
- **Nada e gravado antes do `decode()` dar certo** — upload corrompido vira `NegocioException`, sem
  orfao. No caminho nao-convertido a miniatura e best-effort (falhou -> `Log::warning` +
  `caminho_thumb = null`, e `thumb_url` cai no original).
- Acervo antigo em jpg/png **nao e migrado**; convive, porque cada registro tem seu proprio caminho.
- O front nao propaga mais o formato de entrada: o cropper emite WebP (fallback JPEG) e o `accept`
  da galeria espelha o que o backend aceita.

## Convenção de path (bucket R2 compartilhado)
`{pasta_sistema}/redes/{rede_id}/[empresas/{empresa_id}/]{tabela}/{id}/{colecao}/{uuid}.{ext}`
(+ `{uuid}_thumb.{ext}` p/ imagem). `pasta_sistema` = slug do APP_NAME. Aninhar por empresa e opt-in:
`protected bool $arquivosPorEmpresa = true` no model (default false = rede-level, cobre as 4 atuais).

## Staging (criacao do Produto)
Como o produto ainda nao existe no `create`, o gerenciador AJAX sobe para `{pasta_sistema}/tmp/{token}/`
(`token` = UUID em `session('arquivo_rascunho_token')`, setado no `ProdutoController@create`). Ao salvar,
`store` chama `anexarRascunhos()` que valida o prefixo do token + existencia e **move** para o path
final. Endpoints de rascunho exigem `token === session('arquivo_rascunho_token')` (403 caso contrario).
Limpeza de orfaos: regra de lifecycle no R2 sobre `{pasta_sistema}/tmp/` + comando agendado
`arquivos:limpar-rascunhos` (diario, reforço).

## Preservacao no erro de validacao
Arquivo **nao sobrevive ao `withInput()`** (UploadedFile nao e serializavel e o temporario do PHP
morre no fim do request). Por isso o upload valido e **estacionado** quando a validacao falha:
- `App\Traits\PreservaImagemRascunho` (nos 4 `SalvarXxxRequest`/`AtualizarPerfilRequest`) sobrescreve
  `failedValidation()`, grava em `tmp/{token}` e injeta `{campo}_rascunho` no old input. Usa uma
  **response customizada** na `ValidationException` porque `Request::createFrom()` COPIA o input bag
  — um `merge()` no FormRequest nao chega ao `Handler::invalid()`.
- So estaciona se o **proprio campo** passou: imagem grande demais nao vira rascunho.
- `<x-campo-imagem>` reidrata o preview via `ArquivoService::urlsDoRascunho()` e emite o hidden
  `{campo}_rascunho`; o JS o descarta quando o usuario troca ou remove a imagem.
- `App\Traits\SincronizaImagemUnica` (nos 4 controllers) aplica a precedencia
  **arquivo novo > rascunho > remover** e chama `anexarRascunhoUnico()`.

**Duas chaves de sessao, de proposito:**
| Chave | Dono | Por que separada |
|---|---|---|
| `arquivo_rascunho_token` | galeria do Produto | `anexarRascunhos()` faz `deleteDirectory(tmp/{token})` ao final |
| `ArquivoService::SESSAO_TOKEN_UNICO` (`arquivo_rascunho_unico`) | avatar | token compartilhado faria o save de um produto apagar o avatar estacionado em outra aba |

`ProdutoController@create` **reaproveita** o token vigente em vez de regenerar: apos o redirect de
erro, um token novo orfanaria as imagens ja estacionadas (`caminhoPertenceAoToken()` recusaria os
caminhos do old input). `_galeria.blade.php` semeia `itens` de `old('arquivos_rascunho')` em modo
criacao. **Os dois juntos** — um sem o outro nao resolve.

## Exibicao
- Componentes Blade: `<x-thumb>` (img com fallback icone/iniciais) e `<x-campo-imagem>` (upload unico
  com preview + checkbox remover). Forms de imagem unica precisam de `enctype="multipart/form-data"`.
- Listagens e `buscar()` (Cliente/Servico/Produto) fazem `with('arquivoPrincipal')` e expoem
  `imagem_thumb_url`; os dropdowns AJAX (Venda/Agenda) e o card de venda (`_venda_card`) usam o thumb.
- Front do gerenciador: `resources/js/produto-imagens.js` (entry Vite), carregado via `@vite` no
  `_galeria.blade.php`; usa `fetch` + `<meta csrf-token>` (o layout admin nao carrega `app.js`).

## Gotchas
- **GD precisa de `--with-webp`** — os dois Dockerfiles (`docker/php/Dockerfile` e o `Dockerfile` de
  producao/Railway) instalam `libwebp-dev` e configuram a extensao com a flag. Sem isso
  `imagewebp()` nao existe: `deveConverterParaWebp()` devolve false e o upload degrada para "como
  enviado" em vez de estourar. Confira com
  `docker exec meu-negocio-app php -r "var_dump(gd_info()['WebP Support']);"`.
- R2 nao recebe ACL por objeto — disco `r2` sem `visibility`; publico via `R2_PUBLIC_BASE_URL`.
- Testes: `Storage::fake('r2')` + `UploadedFile::fake()->image()` (GD no container — `image('a.webp')`
  so funciona com a flag acima). Binding de token por request via
  `withSession(['arquivo_rascunho_token' => ...])`.
- Verbos de rota localizados: create = `produtos/novo`, edit = `produtos/{produto}/editar` — sempre
  use o helper `route('produtos.create')`, nao o literal.

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — isolamento rede/empresa (arquivos herdam do dono).
- `.claude/rules/modulos/produto.md` — dono da coleção `galeria`.
- `docs/ADR/0008-armazenamento-de-arquivos-r2.md` — decisao e trade-offs (por que nao M2M/medialibrary).
