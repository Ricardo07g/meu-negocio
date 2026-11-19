# GuardaBot

Proteção em camadas contra bots, browsers automatizados e agentes de IA para aplicações Laravel.

Cada requisição recebe um **score de risco** (0 a 100), somado a partir de sinais baratos e locais. Um
**desafio** opcional (Cloudflare Turnstile) completa a proteção. Tudo começa em modo **observar**:
você mede os falsos positivos antes de bloquear qualquer pessoa.

> Estado: `0.x`, em desenvolvimento dentro do projeto Meu Negócio. A API pode mudar até a primeira
> versão publicada.

## O que ele pega (e o que não pega)

| Camada | Pega | Não pega |
|---|---|---|
| `AgenteDeUsuario` | Ferramentas que se declaram (HeadlessChrome, curl, python-requests, PhantomJS), crawlers conhecidos e agentes de IA declarados (ChatGPT-User, Claude-User, Perplexity-User, `Signature-Agent`) | Automação que usa o User-Agent de um Chrome normal |
| `CabecalhosDeNavegador` | Script que copia o UA do Chrome e esquece o resto (`Accept-Language`, `Sec-Fetch-*`, client hint de headless) | Browser real dirigido por automação |
| `Armadilha` | Robô que preenche todos os campos (honeypot) ou posta assim que a página chega | Robô que só preenche os campos visíveis e espera |
| Turnstile (desafio) | Playwright, Selenium e Puppeteer sem disfarce, segundo a própria Cloudflare | Automação "stealth" determinada |

**Limite honesto:** um agente de IA rodando como extensão no Chrome **real** do usuário (mesmo IP,
cookies e fingerprint) não é detectável com confiabilidade por nenhuma dessas camadas, e nem pela
própria Cloudflare. Sinais de comportamento (cliques "teleportados", ausência de movimento de mouse)
ajudam, mas são probabilísticos e erram com tecnologia assistiva. Por isso eles servem para pedir
uma verificação extra, nunca para bloquear sozinhos. Estão no roteiro.

## Instalação

```bash
composer require ricardo07g/laravel-guarda-bot
php artisan guarda-bot:instalar
```

O instalador pede o modo de operação e as chaves do Turnstile e confere a secret key com a
Cloudflare. Depois:
- **Em ambiente `local`**, grava no `.env` se você confirmar (ou com `--gravar-env`).
- **Fora de `local`**, só **imprime** as variáveis, para você cadastrá-las no painel da hospedagem.
  Em Railway, Fly e Heroku o `.env` do container não persiste.

Sem interação (CI, script):

```bash
php artisan guarda-bot:instalar --modo=observar --site-key=... --secret-key=... --no-interaction
```

## Uso

Na rota, `guarda-bot` (pontuação) antes de `guarda-bot.turnstile` (desafio, que custa uma chamada de
rede):

```php
Route::post('login', [LoginController::class, 'login'])
    ->middleware(['throttle:5,1', 'guarda-bot', 'guarda-bot.turnstile']);
```

No formulário, dentro do `<form>`:

```blade
<x-guarda-bot::protecao />   {{-- armadilha + Turnstile --}}
<x-guarda-bot::armadilha />  {{-- só a armadilha (rota sem Turnstile) --}}
<x-guarda-bot::turnstile />  {{-- só o widget --}}
```

Os campos da armadilha são retirados da requisição antes de chegar ao controller.

Para conferir a configuração efetiva e simular um User-Agent:

```bash
php artisan guarda-bot:diagnosticar --ua="python-requests/2.31"
```

## Modos

| `GUARDA_BOT_MODO` | Comportamento |
|---|---|
| `desligado` | O middleware passa direto e os componentes não renderizam nada. |
| `observar` (padrão) | Avalia e registra no log quem tem algum sinal, sem bloquear. |
| `ativo` | Bloqueia a partir de `GUARDA_BOT_LIMIAR` (padrão 70). O formulário volta com uma mensagem genérica, e quem espera JSON recebe 403. |

Valor desconhecido cai em `observar`: um erro de digitação não tranca usuários reais do lado de fora.

## Configuração

```bash
php artisan vendor:publish --tag=guarda-bot-config   # config/guarda-bot.php
php artisan vendor:publish --tag=guarda-bot-views    # componentes Blade
```

**Pesos:** os pesos de cada sinal ficam em `pesos`. A regra de desenho é que sinal que um humano
pode produzir sem querer, como o autofill rápido de um gerenciador de senha, fica **abaixo** do
limiar e só bloqueia somado a outros.

**Detector próprio:** implemente `Ricardo07g\GuardaBot\Contratos\Detector` e acrescente a classe em
`detectores`:

```php
class PaisBloqueado implements Detector
{
    public function avaliar(Request $request): array
    {
        return $request->header('CF-IPCountry') === 'XX' ? [new Sinal('pais_bloqueado', 100)] : [];
    }
}
```

O peso sugerido no `Sinal` vale enquanto o motivo não estiver em `pesos`. Quando estiver, a config
prevalece.

**Log:** use `GUARDA_BOT_LOG_CANAL` para mandar os vereditos a um canal próprio. São registrados
rota, score, motivos e IP. O corpo da requisição nunca é registrado.

## Variáveis de ambiente

| Variável | Padrão | |
|---|---|---|
| `GUARDA_BOT_MODO` | `observar` | `desligado`, `observar` ou `ativo` |
| `GUARDA_BOT_LIMIAR` | `70` | score a partir do qual o modo `ativo` bloqueia |
| `GUARDA_BOT_TEMPO_MINIMO_MS` | `1500` | envio mais rápido que isto vira sinal |
| `GUARDA_BOT_LOG_CANAL` | (padrão do app) | canal de log dos vereditos |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | (vazio) | sem as duas, o Turnstile fica desligado |

## Content-Security-Policy

O widget do Turnstile precisa de `https://challenges.cloudflare.com` em `script-src` e `frame-src`.
A armadilha usa um atributo `style` para sair da tela, então exige `'unsafe-inline'` em `style-src`
(ou `style-src-attr`). Caso a sua CSP não permita, publique as views e esconda o campo por classe.

## Testes

O pacote se testa sozinho, com [Orchestra Testbench](https://github.com/orchestral/testbench):

```bash
composer install
vendor/bin/phpunit
```

## Licença

MIT. Veja [LICENSE](LICENSE).
