---
paths:
  - "packages/guarda-bot/**"
  - "tests/Feature/GuardaBot/**"
---

# Pacote: GuardaBot (anti-bot)

Proteção contra bots, browsers automatizados e agentes de IA (ADR-0024). **Não é módulo**: é um
pacote Composer em `packages/guarda-bot/` (`ricardo07g/laravel-guarda-bot`, namespace
`Ricardo07g\GuardaBot`), consumido pelo app por um *path repository* (versão fixa `0.1.0` no
`composer.json` da raiz) e destinado a publicação via `git subtree split`.

## Fronteira (a regra que mais importa)
- O pacote **nunca** importa `App\...`, model, trait de tenant ou config do app. O que precisar do
  hospedeiro entra por `config/guarda-bot.php`. Se um teste do pacote precisar do Meu Negócio, ele
  está no lugar errado: vai para `tests/Feature/GuardaBot/` do app.
- Sem dado de tenant: ele só atua em requisições anônimas (POSTs públicos do Auth).

## Peças
- `AvaliadorDeRisco` roda os detectores de `guarda-bot.detectores` e soma os pesos de
  `guarda-bot.pesos` (teto 100). Detector = `Contratos\Detector::avaliar(Request): list<Sinal>`, local
  e sem rede.
- Detectores: `AgenteDeUsuario` (UA + `Signature-Agent`, usa `jaybizzle/crawler-detect`),
  `CabecalhosDeNavegador`, `Armadilha` (honeypot `website` + carimbo `guarda_bot_t` criptografado).
- Middlewares: `guarda-bot` (pontuação; modos `desligado|observar|ativo`) e `guarda-bot.turnstile`
  (desafio, **fail-open**). Ordem na rota: throttle → `guarda-bot` → `guarda-bot.turnstile`.
- Componentes: `<x-guarda-bot::protecao />` (armadilha + Turnstile), `::armadilha`, `::turnstile`.
- Comandos: `guarda-bot:instalar` (só grava `.env` em `local` com confirmação ou `--gravar-env`) e
  `guarda-bot:diagnosticar --ua=`.

## Gotchas
- **Peso abaixo do limiar para sinal humano.** Autofill de gerenciador de senha posta em menos de 1s;
  Safari/Firefox não mandam `Sec-CH-UA`; HTTP comum não tem `Sec-Fetch-*`. Esses sinais nunca
  bloqueiam sozinhos — mantenha assim ao mexer nos pesos.
- **Modo desconhecido cai em `observar`** (o oposto do `CSP_MODO`): bloqueio por engano tranca gente.
- **Suíte do app roda com `GUARDA_BOT_MODO=desligado`** (`phpunit.xml`): os testes postam sem a
  armadilha e com UA `Symfony`. Testes do GuardaBot ligam o modo com `config([...])`.
- **`Request::create` injeta `User-Agent: Symfony` e `Accept-Language`** — nos testes do pacote,
  o helper `requisicao()` remove o que o caso não declarou.
- **Prompts em teste**: com `env=local` o `runningUnitTests()` fica falso e o Laravel Prompts vira
  não interativo (o `confirm` devolve o padrão sem perguntar). Teste o caminho local com
  `--no-interaction` + opção explícita, não com `expectsConfirmation`.
- **POST público novo** no grupo `guest` precisa de `guarda-bot`: o `IntegracaoTest` lista as rotas
  e falha se aparecer uma sem ele.
- Honeypot usa `style` inline para sair da tela — depende de `style-src 'unsafe-inline'` (ADR-0023).

## Rodar
```bash
docker exec -w /var/www/html/packages/guarda-bot meu-negocio-app composer install   # 1a vez
docker exec -w /var/www/html/packages/guarda-bot meu-negocio-app vendor/bin/phpunit
docker exec meu-negocio-app php artisan test --filter=GuardaBot                     # integração
```

## Veja também
- `docs/ADR/0024-guarda-bot-pacote-anti-bot.md` — decisões e limites (agente no Chrome real não é
  detectável de forma confiável).
- `packages/guarda-bot/README.md` — documentação pública do pacote.
- `.claude/rules/modulos/auth.md` — as rotas protegidas.
