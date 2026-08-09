# Agendador — Cloudflare Worker (Cron Trigger)

Relógio externo da aplicação. **Não roda tarefa nenhuma** — Workers não executa PHP.
Ele apenas faz `POST /cron/executar` na aplicação nos horários configurados; quem executa
as tarefas é o Laravel (`App\Support\ExecutarAgendadosCatchUp`).

Por que existe: em produção o app roda num **único serviço** do Railway, com App Sleeping
ligado e sem processo `schedule:work`. Um scheduler interno só rodaria por acaso, enquanto
houvesse tráfego. Este ping externo acorda o container **e** dispara o trabalho.
Decisão completa em [`docs/ADR/0016`](../../docs/ADR/0016-agendador-por-cron-http-externo.md).

## Custo

Plano **Workers Free**: 100.000 requisições/dia e até 5 Cron Triggers por conta; 10 ms de
CPU por invocação — e o tempo esperando o `fetch` não conta como CPU. Este Worker gasta
**2 invocações por dia**.

## Configuração

```bash
npm install -g wrangler        # ou npx wrangler
wrangler login

cd cloudflare/agendador

# Segredo — o MESMO valor da variável CRON_TOKEN no serviço do Railway.
wrangler secret put CRON_TOKEN

wrangler deploy
```

`APP_URL` fica em `wrangler.toml` (URL pública, não é segredo). O `CRON_TOKEN` **nunca**
entra no repositório: vive no `wrangler secret` e nas variáveis do Railway.

Gerar um token: `openssl rand -hex 32`.

## Testar sem esperar o horário

```bash
wrangler dev --test-scheduled
curl "http://localhost:8787/__scheduled"
```

Contra o app local (o `.env` precisa de `CRON_TOKEN` preenchido):

```bash
curl -i -X POST http://localhost:8080/cron/executar -H 'X-Cron-Token: <token>'
```

Sem o header (ou com o token errado) a resposta é **404** — de propósito: um 403
confirmaria que a rota existe.

## Operação

```bash
wrangler tail                  # log das invocações ao vivo
wrangler deployments list
```

## Alterar horários

Edite `crons` em `wrangler.toml` e rode `wrangler deploy`. **A frequência daqui é
independente da frequência das tarefas** (`config/cron.php`): a aplicação roda tudo que
ficou devido desde o último tick, então diminuir os pings só atrasa a execução — não pula
tarefa. Aumentar a frequência aproxima a execução do horário nominal, ao custo de manter o
container do Railway acordado com mais frequência.
