# ADR-0024 — GuardaBot: anti-bot em camadas, como pacote Composer

- **Status:** Aceito
- **Data:** 2026-11-18
- **Contexto:** `packages/guarda-bot` (pacote `ricardo07g/laravel-guarda-bot`) + rotas públicas do Auth

## Contexto

O objetivo é impedir que bots usem o sistema. São dois grupos:
- **Automação clássica:** Playwright, Puppeteer, Selenium, headless.
- **Agentes de IA que operam um navegador simulando o usuário:** ChatGPT agent, Comet, extensões que
  dirigem o Chrome.

Até aqui a única barreira era o Turnstile nas três telas públicas de formulário.

Três fatos moldaram a decisão.

**A borda não ajuda, por enquanto.** A produção roda direto no Railway, sem o proxy da Cloudflare. O
que a Cloudflare oferece de sinal de bot para a origem (bot score, JA4, detection IDs) é recurso
Enterprise. O Bot Fight Mode gratuito exige o proxy e não aceita exceção por regra. Tudo o que for
feito agora roda no app.

**Agente no Chrome real não é detectável com confiabilidade.** Um agente que roda como extensão no
navegador do próprio usuário usa o mesmo IP, cookies, TLS e fingerprint. A própria Cloudflare declara
esse caso fora de escopo. O que sobra são sinais de comportamento: clique "teleportado", nenhum evento
de mouse, texto colado. A pesquisa mostra que eles funcionam em laboratório, mas são probabilísticos,
ninguém publica a taxa de falso positivo em produção, e eles erram justamente com leitor de tela,
navegação por teclado e gerenciador de senha.

**O resultado deve ser reaproveitável.** A intenção é publicar a proteção como pacote Composer, com
um instalador que pede as chaves. Construir como módulo e extrair depois seria fazer o trabalho duas
vezes, e a fronteira entre pacote e app dependeria de disciplina, não de estrutura.

## Decisão

**1. Pacote Composer dentro do repositório.**
- O código mora em `packages/guarda-bot/` e o app o consome por um *path repository* com versão fixa
  (`0.1.0`), para o lock não mudar a cada branch nem quebrar o build sem `.git`.
- O pacote tem `composer.json`, testes (Orchestra Testbench) e `vendor/` próprios, e o CI o testa
  isolado.
- Publicar será um `git subtree split`, que preserva o histórico.
- **Exceção registrada:** é o único código PHP de produto fora de `app/Modules/`. O namespace é
  `Ricardo07g\GuardaBot`. A API continua em português; o idioma é revisto antes da publicação.

**2. Score de risco com detectores plugáveis.**
- Cada detector devolve sinais por motivo. O peso de cada motivo fica na config, e o score é a soma
  com teto de 100. Começamos com três detectores locais, sem rede:
  - `AgenteDeUsuario`: UA de automação, crawler, agente de IA declarado, `Signature-Agent`.
  - `CabecalhosDeNavegador`: UA de Chrome sem `Accept-Language`, sem `Sec-Fetch-*`, client hint de
    headless.
  - `Armadilha`: honeypot e tempo mínimo com carimbo criptografado.
- Regra de desenho: **sinal que um humano produz sem querer fica abaixo do limiar**. O autofill
  rápido de um gerenciador de senha, por exemplo, só bloqueia somado a outro sinal.

**3. Três modos, com rollout por observação.**
- `desligado` (a suíte de testes), `observar` (registra e nunca bloqueia: o primeiro deploy) e
  `ativo` (bloqueia a partir de 70).
- Valor desconhecido cai em `observar`, ao contrário do `CSP_MODO`, que cai em `ativo`. Uma CSP
  frouxa por engano é invisível ao usuário. Um bloqueio por engano tranca gente real do lado de
  fora.
- O bloqueio responde com mensagem genérica, que não ensina ao robô qual sinal o pegou.

**4. O Turnstile vai para o pacote.**
- É a chave que o instalador pede, então mora junto. As variáveis de ambiente são as mesmas (nada
  muda no Railway), e o fail-open continua.
- `guarda-bot` (local, barato) roda **antes** de `guarda-bot.turnstile` (chamada de rede).

**5. Todo POST público passa pelo GuardaBot.**
- Login, cadastro, recuperação, redefinição de senha e segundo fator. A redefinição de senha ganha
  limite de tentativas com balde próprio.
- Um teste lista os POSTs públicos e falha se aparecer um novo sem o middleware.

**6. Comportamento nunca bloqueia sozinho.** Quando os sinais do navegador entrarem (Parte 2 do
roteiro), eles pedirão verificação extra (desafio, reautenticação), não um 403.

## Consequências

### Positivas
- Robôs que se declaram, scripts que imitam um navegador e preenchedores de formulário são barrados
  sem custo por requisição e sem depender da borda.
- O modo `observar` mede o falso positivo com tráfego real antes de qualquer bloqueio.
- A extração para publicação é mecânica: o pacote não enxerga `App\...` e já se testa sozinho.
- Um detector novo é uma classe e uma linha na config. Os próximos passos do roteiro (sinais JS, Web
  Bot Auth, driver pago) entram sem mexer no núcleo.

### Negativas
- **Não resolve o caso mais difícil.** Um agente de IA no Chrome real do usuário e a automação
  stealth determinada (Patchright, nodriver) passam por estas camadas. Isso fica escrito no README do
  pacote para ninguém comprar a proteção pelo que ela não faz.
- **Duas suítes e dois `vendor/`.** O pacote tem dependências de desenvolvimento próprias, e o CI
  roda um passo a mais.
- **Exceção de estrutura e de idioma** em relação ao `CLAUDE.md`: o pacote foge de `app/Modules/` e
  pode ter de mudar de idioma para publicar.
- O honeypot usa `style` inline. Funciona com a CSP atual (`style-src 'unsafe-inline'`), mas é uma
  dependência a lembrar se a política de estilo apertar.

### Neutras
- O `.env` só é escrito pelo instalador em ambiente `local` e com consentimento (pergunta ou
  `--gravar-env`). Fora dele, as variáveis são impressas, porque em hospedagem por container o `.env`
  não persiste.
- O log dos vereditos guarda rota, score, motivos e IP, nunca o corpo da requisição.

## Alternativas consideradas

- **Módulo em `app/Modules/` e extração depois.** Primeiro PR menor, mas paga a extração inteira
  depois: namespace, registro, testes e Turnstile. Rejeitado porque a publicação é objetivo, não
  hipótese.
- **Repositório separado desde já.** O container de dev monta só esta pasta, e o deploy dependeria de
  puxar o pacote de fora antes de ele estar pronto. Rejeitado enquanto a API não estabiliza.
- **`spatie/laravel-honeypot`.** O responder dele encerra a requisição sozinho. Aqui o honeypot
  precisa ser um sinal somado aos outros.
- **Fornecedor pago (Fingerprint, Castle, DataDome, HUMAN).** Pega stealth e parte dos agentes, a
  partir de ~US$ 99/mês e até contratos enterprise. Fica como driver opcional do roteiro, desligado
  sem chave, no molde do módulo Ia.
- **Bloquear por comportamento desde já.** Rejeitado pelo custo em acessibilidade e pela falta de
  taxa de falso positivo publicada.
