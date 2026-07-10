# ADR-0008 — Armazenamento de arquivos (imagens/anexos) no Cloudflare R2

## Status

Aceito — julho/2026.

## Contexto

As entidades não tinham imagens: Cliente, Usuário, Produto e Serviço exibiam apenas avatares de
iniciais (classes `avatar-text` do Duralux). Surgiu a necessidade de:

- Imagens para essas 4 entidades (Produto com **várias** imagens em galeria/carrossel; as demais com
  **uma** imagem), aparecendo em listagens, itens de venda, buscas AJAX e no avatar do usuário.
- Uma base **genérica**: outros módulos vão anexar **PDFs e outros formatos** — não pode ser um
  recurso só de imagem.

Restrições e contexto de modelagem:

- Multi-tenant single-DB com `rede_id` sempre e `empresa_id` no transacional (ver ADR-0001/0004). Os
  arquivos precisam respeitar esse isolamento.
- O bucket R2 é **compartilhado** entre sistemas — os caminhos precisam ser namespaced por sistema.
- É um produto de portfólio; optou-se por implementação própria (alinhada às traits `RedeTrait`/
  `EmpresaTrait` já existentes) em vez de um pacote (spatie/laravel-medialibrary), para ter controle
  total do path/tenant. Descartou-se também um modelo M2M (arquivo compartilhado entre entidades):
  YAGNI para o domínio, e traz reference-counting/GC e autorização mais sutil.

## Decisão

### Módulo `Arquivo` genérico, 1:N polimórfico, agrupado por coleção

- Tabela única **`arquivos`** com `morphTo anexavel` (`anexavel_type`/`anexavel_id`), `rede_id`
  (sempre), `empresa_id` (nullable, metadado de path), `colecao`, `disco`, `caminho`,
  `caminho_thumb` (nullable — só imagem), metadados (`mime`, `extensao`, `tamanho`, `largura`,
  `altura`, `hash`), `ordem` e `principal`. Model `Arquivo` estende `BaseModel` (escopo só por
  `rede_id`; **não** usa `EmpresaTrait`, para não sumir com arquivos rede-level sob contexto de
  empresa).
- Trait **`App\Traits\TemArquivos`** + contrato `PossuiArquivos`: o model declara suas coleções em
  `colecoesArquivo()` (`mimes`, `max_kb`, `unica`, `max`, `thumb`). "Imagem" é só um arquivo cujo
  mime é `image/*` (ganha miniatura via `intervention/image`).
- **`ArquivoService`** centraliza toda a I/O (armazenar, sincronizar único, remover, reordenar,
  definir capa) e o staging.

### Cloudflare R2 (S3-compatível) com path namespaced

- Disco `r2` em `config/filesystems.php` (driver `s3`, `region=auto`, endpoint
  `{R2_ACCOUNT_ID}.r2.cloudflarestorage.com`, `use_path_style_endpoint=true`, `url=R2_PUBLIC_BASE_URL`).
  **Sem ACL por objeto** (o acesso público é servido pelo domínio público do bucket).
- Convenção de path (em `config/arquivos.php`, `pasta_sistema` = slug do APP_NAME):
  `{sistema}/redes/{rede_id}/[empresas/{empresa_id}/]{tabela}/{id}/{colecao}/{uuid}.{ext}` (+
  `{uuid}_thumb.{ext}` para imagens). O aninhamento por empresa é opt-in (`$arquivosPorEmpresa`).

### Staging em `tmp/` para o upload na criação do Produto

- O gerenciador de galeria é AJAX e funciona igual em criar e editar. Na **criação** (produto ainda
  sem id), os uploads vão para `{sistema}/tmp/{token}/` — `token` = UUID amarrado à sessão
  (`arquivo_rascunho_token`); ao salvar, o `ArquivoController@store` move os objetos para o path
  final. Objetos abandonados em `tmp/` são limpos por uma **regra de lifecycle no R2** sobre o
  prefixo `{sistema}/tmp/` + o comando agendado `arquivos:limpar-rascunhos` (reforço).

## Consequências

**Positivas**

- Um único mecanismo cobre imagem, PDF e qualquer formato, em qualquer módulo (só declarar a
  coleção). Sem alterar o schema das tabelas de domínio (nada de coluna `foto` por tabela).
- Isolamento multi-tenant herdado: arquivos carregados via o dono (já tenant-scoped) e path
  namespaced por rede/empresa facilitam migração/limpeza por tenant.
- UX de galeria idêntica em criar/editar graças ao staging; limpeza do lixo é trivial (um prefixo).

**Negativas**

- O staging depende de uma regra de lifecycle no R2 configurada fora do código (documentada no PR);
  o comando agendado é só reforço.
- `nome_original` dos itens anexados via rascunho é derivado do nome no bucket (o metadado real do
  upload não é persistido no staging, para não confiar em dados do cliente).

**Neutras**

- `intervention/image` (GD) foi adicionado só para miniaturas; não-imagens são guardadas sem thumb.
- Um modelo M2M continua possível no futuro (pivot polimórfico) se surgir necessidade concreta de
  compartilhar o mesmo arquivo entre entidades.
