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
-> decisao completa em `docs/ADR/0008-armazenamento-de-arquivos-r2.md`.

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
  `removerRascunho` / `anexarRascunhos` (staging), `remover`, `reordenar`, `definirPrincipal`.
  Miniatura via `intervention/image` (GD) **so quando imagem**; `encodeUsingFileExtension()`.
- **`ProdutoArquivoController`** (modulo Produto): endpoints AJAX da galeria (store/destroy/reordenar/
  principal + rascunho store/destroy), autorizados via `ProdutoPolicy`.

## Coleções declaradas hoje
- Produto -> `galeria` (multipla, max 8, gera thumb).
- Cliente / Servico / Usuario -> `avatar` (`unica => true`, gera thumb).
- Coleção nao declarada usa limites globais de `config/arquivos.php` (mimes incluem pdf).

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

## Exibicao
- Componentes Blade: `<x-thumb>` (img com fallback icone/iniciais) e `<x-campo-imagem>` (upload unico
  com preview + checkbox remover). Forms de imagem unica precisam de `enctype="multipart/form-data"`.
- Listagens e `buscar()` (Cliente/Servico/Produto) fazem `with('arquivoPrincipal')` e expoem
  `imagem_thumb_url`; os dropdowns AJAX (Venda/Agenda) e o card de venda (`_venda_card`) usam o thumb.
- Front do gerenciador: `resources/js/produto-imagens.js` (entry Vite), carregado via `@vite` no
  `_galeria.blade.php`; usa `fetch` + `<meta csrf-token>` (o layout admin nao carrega `app.js`).

## Gotchas
- R2 nao recebe ACL por objeto — disco `r2` sem `visibility`; publico via `R2_PUBLIC_BASE_URL`.
- Testes: `Storage::fake('r2')` + `UploadedFile::fake()->image()` (GD no container). Binding de token
  por request via `withSession(['arquivo_rascunho_token' => ...])`.
- Verbos de rota localizados: create = `produtos/novo`, edit = `produtos/{produto}/editar` — sempre
  use o helper `route('produtos.create')`, nao o literal.

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — isolamento rede/empresa (arquivos herdam do dono).
- `.claude/rules/modulos/produto.md` — dono da coleção `galeria`.
- `docs/ADR/0008-armazenamento-de-arquivos-r2.md` — decisao e trade-offs (por que nao M2M/medialibrary).
