# ADR-0023 — Cabeçalhos de segurança HTTP e CSP com nonce

## Status

Aceito — novembro/2026. Endurece a borda HTTP que o ADR-0017 (FrankenPHP) montou e que até aqui só
cuidava de cache.

## Contexto

Uma varredura automatizada (nuclei, ~11 mil templates) contra o ambiente publicado só devolveu
achados de severidade *info*. Lido com calma, o relatório separa-se em três grupos: lacunas reais de
configuração, comportamentos que estão certos e não devem mudar, e ruído da plataforma de hospedagem.

As lacunas reais:

- **Nenhum cabeçalho de segurança.** As respostas saíam só com `Cache-Control`, cookies e os
  cabeçalhos da borda do Railway: sem `Content-Security-Policy`, `X-Frame-Options`,
  `X-Content-Type-Options`, `Strict-Transport-Security`, `Referrer-Policy` nem `Permissions-Policy`.
- **A versão exata do PHP em todo response** (`X-Powered-By`). A imagem `dunglas/frankenphp` não traz
  `php.ini`: valem os defaults de compilação, que também deixam `display_errors` ligado.
- **Dotfiles servidos.** O Caddy entregava qualquer arquivo de `public/` — inclusive o `.htaccess`.
  O nginx de desenvolvimento já bloqueava; os dois runtimes divergiam.
- **Um cookie legível por script sem consumidor.** O `XSRF-TOKEN` existe para o axios ler o token e
  devolvê-lo num header — por isso não pode ser `HttpOnly`. Nada aqui usa axios: forms levam
  `_token` e o JS manda `X-CSRF-TOKEN` lido do `<meta name="csrf-token">`.

E uma lacuna que nenhum scanner aponta, mas que a revisão expôs: **o modo debug é uma variável do
painel de deploy.** Com `APP_DEBUG` ligado, qualquer exceção — basta uma URL malformada — vira a tela
de erro completa do Laravel, com stack trace, trecho do código e as queries da requisição com
bindings. O `.env.example` traz debug ligado (o certo para dev) e nenhum teste alcança o valor de
produção.

### CSP: o cabeçalho que dá trabalho

Dos cabeçalhos, só a CSP impede um script injetado (XSS) de executar — e só se `script-src` **não**
tiver `'unsafe-inline'`. O inventário das telas:

- 26 blocos `<script>` inline executáveis em 18 Blades (12 interpolam dado do servidor, então hash
  não serve — tem que ser nonce);
- 2 handlers em atributo (`onchange="this.form.submit()"`);
- nenhum `eval`/`new Function` no código nem nos bundles do template; o SweetAlert e o jQuery do
  Duralux não criam script inline no uso que fazemos;
- ~110 atributos `style=`, mais CSS injetado em runtime pelo SweetAlert e pelo ApexCharts;
- origens externas no navegador: Turnstile, Google Fonts (inclusive via `@import` no `theme.min.css`),
  ViaCEP e o domínio público do R2.

### Alternativas consideradas

1. **CSP com `'unsafe-inline'` em `script-src`** — um arquivo, zero risco de regressão. Bloqueia
   origem externa, clickjacking, `<base>` e `form-action` para fora, mas **não bloqueia XSS**, que é o
   motivo de ter CSP. E a migração para nonce depois mexeria nos mesmos 18 arquivos. Descartada.
2. **Hashes em vez de nonce** — não servem para blocos que interpolam dado do servidor (o hash muda a
   cada render). Descartada.
3. **`'strict-dynamic'`** — exigiria nonce também em todo `<script src>` do template. Ganho marginal
   sobre `'self'` + nonce aqui, onde não há carregador de script de terceiros. Descartada.
4. **Pacote (`spatie/laravel-csp`, `bepsvpt/secure-headers`)** — resolveria o mesmo com mais
   superfície. Um middleware de ~100 linhas e o `Vite::useCspNonce()` nativo bastam.
5. **Cabeçalhos só no Caddy** — cobriria os estáticos de graça, mas fica fora do alcance dos testes,
   diverge do dev (nginx) e não sabe gerar nonce. Ficou no Caddy só o que é de estático.

## Decisão

**Middleware global com os cabeçalhos de segurança e CSP estrita por nonce; servidor e imagem
endurecidos nos dois runtimes; debug travado em produção.**

1. **`App\Http\Middleware\CabecalhosSeguranca`**, registrado com `prepend` — o mais externo da pilha,
   então até o 400 do `ValidatePathEncoding` sai protegido. Em toda resposta:
   `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
   `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` desligando câmera,
   microfone, localização, pagamento e USB, `Cross-Origin-Opener-Policy` e
   `Cross-Origin-Resource-Policy` `same-origin`, `X-Permitted-Cross-Domain-Policies: none`.
   `Strict-Transport-Security` só em HTTPS, **sem `includeSubDomains`/`preload`**: o domínio pai
   (`up.railway.app`) não é nosso.

2. **CSP com nonce por requisição** (`App\Support\PoliticaCsp`), só em documento HTML:

   ```
   default-src 'self'; script-src 'self' 'nonce-…' <origens>; style-src 'self' 'unsafe-inline' <origens>;
   font-src 'self' <origens>; img-src 'self' data: blob: <origem dos arquivos>;
   connect-src 'self' blob: <origens>; frame-src 'self' <origens>; frame-ancestors 'self';
   object-src 'none'; base-uri 'self'; form-action 'self'
   ```

   - Script inline só executa com `<script @nonce>` (diretiva registrada no `AppServiceProvider`). Os
     dois `onchange` viraram `data-submeter-ao-mudar`, tratado por listener delegado no
     `mn-admin.js` — atributo de evento não tem como carregar nonce.
   - `style-src` mantém `'unsafe-inline'` e **não** leva nonce: com nonce o navegador ignora o
     `'unsafe-inline'` e os ~110 `style=`, o SweetAlert e o ApexCharts quebrariam. CSS injetado não
     executa código.
   - `frame-ancestors`/`frame-src 'self'` (e `X-Frame-Options: SAMEORIGIN`, não `DENY`): o modal de
     recibo embute o PDF da própria aplicação num iframe.
   - Origens externas em `config/seguranca.php`; a dos arquivos (R2/storage) e a do Vite em dev
     (`public/hot`) são resolvidas em runtime.
   - **Fica sem CSP:** resposta não-HTML (o visualizador de PDF do Chrome herda a política do
     documento) e a tela de exceção do Laravel em dev, que usa script inline sem nonce.
   - O calendário (Toast UI) desliga `usageStatistics`, que mandava um ping ao Google Analytics.

3. **Rollout por `CSP_MODO`** (`ativo` | `relatorio` | `desligado`, padrão `ativo`). Em `relatorio` o
   cabeçalho vira `Content-Security-Policy-Report-Only`: o navegador só avisa no console. Mudança de
   política sobe primeiro em `relatorio`, navega-se pelas telas e depois volta ao `ativo`. Valor
   desconhecido cai no modo seguro.

4. **Servidor e imagem, nos dois runtimes.** `docker/railway/php.ini` (copiado para `conf.d/`):
   `expose_php = Off`, `display_errors = Off`, `log_errors = On`. Caddy responde 404 a qualquer
   caminho com segmento iniciado por ponto (exceto `.well-known`), espelhando o nginx, e põe `nosniff`
   nos estáticos — que não passam pelo Laravel. O nginx de dev ganha o mesmo `nosniff`.

5. **Debug travado em produção.** `App\Support\GuardaDeDebug` desliga `app.debug` no boot quando o
   ambiente é `production`, qualquer que seja a variável. O entrypoint avisa uma vez por deploy se
   `APP_ENV` não for `production` (sem isso a trava não reconhece produção) ou se `APP_DEBUG` estiver
   ligado.

6. **CSRF sem o cookie `XSRF-TOKEN`.** `VerificarCsrf` estende a proteção padrão só com
   `$addHttpCookie = false`. A verificação não muda: `_token`, `X-CSRF-TOKEN` e `X-XSRF-TOKEN`
   continuam aceitos; POST sem token continua 419.

7. **A regra vira lint.** `BladeLintTest` e o hook `blade-lint.sh` barram `<script>` inline sem
   `@nonce` e atributo `on*=`. É estático de propósito: cobre os ramos `@if` (flash, permissão,
   plano) que nenhuma visita ao navegador garante exercitar. `CabecalhosSegurancaTest` prova o HTML
   renderizado das telas com mais script contra o nonce do próprio cabeçalho.

### O que o relatório apontou e fica como está

| Achado | Por quê |
|---|---|
| Cookie sem `SameSite=Strict` | `Lax` é o correto: com `Strict`, o link de verificação de e-mail ou de redefinição de senha, clicado no webmail, chegaria sem sessão. A sessão já é `Secure; HttpOnly; SameSite=Lax`. |
| `Cross-Origin-Embedder-Policy` ausente | `require-corp` serve para isolamento de origem (`SharedArrayBuffer`) e quebraria imagens do R2, o Turnstile e o Google Fonts. Não se aplica. |
| Subresource Integrity ausente | Os assets do Vite são da mesma origem (SRI não acrescenta nada a quem já controla o HTML) e o CSS do Google Fonts muda por user-agent, o que torna SRI impossível. |
| WAF, versões de TLS, emissor do certificado, DNS/TLS wildcard | Borda do Railway. O wildcard some com domínio próprio. |
| `OPTIONS` respondendo `GET,HEAD`, `robots.txt`, detecção de tecnologia | Informativo. |

## Consequências

### Positivas

- Script injetado não executa: `script-src` sem `'unsafe-inline'` nem `'unsafe-eval'`.
- Clickjacking, `<base>` injetado, `form-action` para fora e plugins (`object-src 'none'`) fechados.
- A versão do PHP some das respostas e erro de PHP não vai mais para o corpo.
- Um esquecimento de configuração no painel de deploy deixa de expor stack trace e queries.
- Um cookie legível por script a menos; uma `Set-Cookie` a menos por resposta.
- Os dois runtimes (Caddy em produção, nginx em dev) voltam a se comportar igual nos dotfiles.

### Negativas

- **Todo script inline novo precisa de `@nonce`.** Esquecer não quebra teste HTTP — quebra a tela no
  navegador. O lint e o hook cobram, e a regra está em `.claude/rules/javascript-telas.md`.
- **Serviço externo novo no navegador exige mexer em `config/seguranca.php`.** Um widget de
  pagamento ou de chat que for adicionado sem isso simplesmente não carrega.
- Os 34 `href="javascript:void(0)"` do template continuam funcionando, mas cada clique gera um aviso
  de CSP no console (`javascript:` é script inline). Ruído, não quebra.
- O nonce torna o HTML não cacheável por proxy compartilhado — o que já era o caso (sessão em toda
  página). Se um dia houver cache de página inteira (ex.: a landing num CDN), o nonce reaproveitado
  enfraquece a proteção daquela página.
- A trava de debug depende de `APP_ENV=production` estar certo; o entrypoint avisa, não impede.

### Neutras

- `style-src` continua com `'unsafe-inline'`. Tirar exigiria remover ~110 `style=` e trocar o
  SweetAlert/ApexCharts embarcados — fora de proporção para um vetor que não executa código.
- `Content-Security-Policy-Report-Only` só avisa no console de quem navega. Coletar violações no
  servidor (`report-uri`) fica como opção.
- Próximos passos registrados, não pendências: trocar os `javascript:void(0)` por `<button>`;
  hospedar o Google Fonts localmente (tira duas origens da CSP e o IP do visitante do Google);
  domínio próprio (some o wildcard) com prefixo `__Host-` no cookie de sessão.
