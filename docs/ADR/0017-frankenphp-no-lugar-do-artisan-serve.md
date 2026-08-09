# ADR-0017 — FrankenPHP no lugar do `php artisan serve` em produção

## Status

Aceito — agosto/2026. Primeira troca do runtime web de produção. Resolve, de uma vez, a ausência de
cache nos assets e o limite de um request por vez que o ADR-0016 registrou como negativa conhecida.

## Contexto

O `Dockerfile` de produção subia a aplicação com `php artisan serve` — o **servidor embutido do
PHP**, cuja própria documentação diz ser destinado a desenvolvimento. Duas consequências medidas em
produção (09/08/2026):

**1. Nenhum cache nos assets.** A resposta de `GET /assets/css/theme.min.css` vinha com
`content-length: 183689` e **sem `Cache-Control`, `ETag` ou `Last-Modified`**. Sem diretiva e sem
validador, o navegador refaz o download a cada navegação. Somando o que toda página do admin carrega:

| | Bruto | Gzip |
|---|---:|---:|
| Total por page view | 1.346 KB | **285 KB** |

O maior item isolado é o `vendors.min.js` do template (160 KB gzip).

Não havia como corrigir pela aplicação: o servidor embutido entrega arquivos existentes **sem
invocar o PHP**, então nenhum middleware do Laravel alcança essas respostas.

**2. Um request por vez.** O servidor embutido é single-threaded por padrão. Qualquer coisa demorada
— a varredura do agendador (ADR-0016), um download de exportação (ADR-0012), um upload com
reencode em WebP (ADR-0015) — bloqueia a aplicação inteira enquanto roda. Cada um dos 8 arquivos
estáticos de uma página também era servido em série.

A compressão já não era problema: a borda do Railway (`railway-hikari`) entrega tudo com
`content-encoding: gzip`.

Alternativas consideradas:

1. **`PHP_CLI_SERVER_WORKERS`** — resolve a concorrência, mas **não** o cache: o servidor embutido
   não emite `Cache-Control` em hipótese alguma.
2. **nginx + php-fpm no mesmo container** — funciona (é o que o `docker-compose` de dev usa), mas
   exige supervisord para dois processos em primeiro plano num serviço que hoje roda um só.
3. **Servir os assets do R2 via `ASSET_URL`** — tiraria os bytes do container, mas cairia no
   `pub-*.r2.dev`, que a documentação do Cloudflare classifica como **rate-limited e para
   desenvolvimento**, sem cache nem WAF. Depende de domínio próprio para fazer sentido.
4. **FrankenPHP** — servidor único (binário Caddy + PHP embarcado), um processo em primeiro plano,
   estáticos com `ETag`/`Cache-Control` configuráveis e concorrência nativa.

## Decisão

**Trocar a base do estágio de runtime de `php:8.3-cli` para `dunglas/frankenphp:php8.3`.**

1. **Extensões preservadas.** A imagem já traz `mbstring`, `xml`, `pdo_sqlite` e `opcache`; as demais
   entram por `install-php-extensions`: `pdo_mysql`, `zip`, `bcmath`, `gd`, `intl`, `pcntl`, `redis`.
   O `gd` **precisa** sair com suporte a WebP — a normalização de imagens do ADR-0015 depende dele, e
   sem a flag o upload degrada silenciosamente para "como enviado". É item obrigatório de verificação.

2. **`docker/railway/Caddyfile`** define a política de cache por caminho:

   | Caminho | `Cache-Control` | Por quê |
   |---|---|---|
   | `/build/*` | `public, max-age=31536000, immutable` | Vite gera nome com hash de conteúdo: o mesmo nome nunca muda |
   | `/assets/*`, `/css/*`, favicons, `robots.txt` | `public, max-age=604800` | Mudam num deploy sob o **mesmo** nome — daí **sem** `immutable`; passada a semana, o `ETag` do Caddy resolve em 304 |
   | todo o resto | `no-store, private` | Página autenticada de SaaS multi-tenant: cache em qualquer proxy do caminho seria risco de vazar dado entre redes |

3. **`auto_https off` e `admin off`.** O TLS termina na borda do Railway e chega como HTTP puro; o
   endpoint de administração do Caddy não tem por que existir no container.

4. **O entrypoint mantém os três papéis** (`web` / `worker` / `scheduler`). Só o comando final do
   papel `web` muda, de `php artisan serve` para `frankenphp run --config /etc/frankenphp/Caddyfile`.
   A porta continua vindo de `PORT`, agora lida pelo Caddyfile.

5. **Higiene de imagem, junto:** ~27 MB do template Duralux que **nunca chegam a um navegador** saem
   da imagem pelo `.dockerignore` — 12,3 MB de source maps, 11,2 MB de bandeiras SVG (o
   `flagicon.min.css` referencia, mas nenhuma view usa classe de bandeira), 2,4 MB de `.zip` e um mp4
   de demonstração, 0,9 MB de `.scss`. **Continuam no git**: a cópia do template segue íntegra para
   referência visual e para o `NOTICE.md`.

## Consequências

### Positivas
- Navegação seguinte à primeira deixa de baixar 285 KB por página. Mesmo depois da expiração, a
  revalidação por `ETag` devolve 304 sem corpo.
- Concorrência real: a varredura do agendador, um download de exportação ou um upload deixam de
  travar a aplicação. Isso **resolve a negativa registrada no ADR-0016**.
- Imagem ~27 MB menor: build e deploy mais rápidos.
- `zstd`/`brotli` disponíveis além do gzip.

### Negativas / limites
- **Troca de runtime é a mudança mais arriscada do deploy até aqui** — muda a base da imagem, o
  servidor e o modelo de concorrência. Exige smoke funcional completo, não só "a home abriu".
- PHP em modo **ZTS** (thread-safe) na imagem do FrankenPHP, contra NTS antes. Extensões se
  comportam igual no uso deste projeto, mas é uma diferença real de ambiente.
- `max-age=604800` em `/assets/*` significa que um ajuste no template pode levar até uma semana para
  chegar a quem já visitou. Se virar problema, o caminho é versionar a URL (como o `mn-admin.css` já
  faz com `?v=filemtime`), não encurtar o cache.
- Mais uma configuração para manter (`Caddyfile`), e um ambiente a mais de divergência: o
  `docker-compose` de desenvolvimento continua em nginx + php-fpm.

### Neutras
- A compressão continua sendo feita na borda do Railway; o `encode` do Caddy é redundância barata.
- Os papéis `worker` e `scheduler` do entrypoint não mudam.
- Nada na aplicação muda: nenhum arquivo de `app/`, `routes/` ou `config/` foi tocado.

## Verificação

Antes: `curl -sID - .../assets/css/theme.min.css` → `content-length: 183689`, sem cabeçalho de cache.
Depois: a mesma chamada deve trazer `cache-control: public, max-age=604800` e um `etag`, e uma
segunda requisição condicional deve responder **304**.

Smoke funcional obrigatório em produção após o deploy: login, dashboard, uma listagem, criação de
venda, **upload de imagem de produto** (prova que o `gd` saiu com WebP) e download de exportação.
Mais `POST /cron/executar` respondendo 200 sem bloquear uma navegação simultânea.
