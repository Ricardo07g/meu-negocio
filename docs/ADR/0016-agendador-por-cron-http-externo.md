# ADR-0016 — Agendador por cron HTTP externo (Cloudflare Workers)

## Status

Aceito — agosto/2026. Primeira peça de infraestrutura fora do Railway. Fecha o buraco aberto pelo
enxugamento do deploy: em produção não existe processo `schedule:work`, então **nenhuma tarefa
agendada rodava**.

## Contexto

O projeto tem três tarefas agendadas, todas varreduras de limpeza:

| Comando | Frequência | Efeito |
|---|---|---|
| `arquivos:limpar-rascunhos` | diária | apaga uploads `tmp/` abandonados no bucket R2 |
| `exportacoes:limpar` | horária | apaga exportações de extrato expiradas (ADR-0012) |
| `assinaturas:expirar-trial` | diária | rebaixa unidades com trial vencido (ADR-0013) |

Em desenvolvimento isso funciona: o `docker-compose.yml` sobe um serviço `scheduler` rodando
`php artisan schedule:work`.

**Em produção, não.** O deploy no Railway foi enxugado para **um único serviço** — SQLite num volume
`/data`, `QUEUE_CONNECTION=sync`, **App Sleeping ligado**, custo na casa de US$ 1/mês. Os serviços
`worker`, MySQL e Redis foram deletados. Consequência: lixo acumulava indefinidamente no R2 e na
tabela `exportacoes`, e a expiração de trial só acontecia pelo fallback de request em
`VerificarEmpresa`.

As alternativas consideradas:

1. **Serviço `scheduler` dedicado no Railway.** Dobra o custo e esbarra no banco: um volume do Railway
   é montado num serviço só, então um segundo container não enxergaria o `database.sqlite`.
2. **`schedule:work` em background dentro do container web.** Custo zero, mas com **App Sleeping** o
   container é parado quando não há tráfego de entrada — o processo interno só rodaria por acaso,
   enquanto alguém estivesse usando o app. Agendamento não determinístico não é agendamento.
3. **Cron Job do Railway.** Mesmo problema do item 1: roda como serviço separado, sem o volume.
4. **GitHub Actions agendado.** Grátis em repositório público, mas o cron do Actions atrasa com
   frequência e as workflows agendadas são desativadas após 60 dias sem atividade no repositório.
5. **Cron Trigger do Cloudflare Workers.** O projeto já usa Cloudflare (R2, ADR-0008) e o plano
   **Workers Free** inclui Cron Triggers.

## Decisão

**Um Worker do Cloudflare é o relógio; o Laravel continua sendo quem executa.**

1. **Workers não executa PHP** — o Worker não roda tarefa nenhuma. Ele faz um `POST` autenticado em
   `/cron/executar`. Código em `cloudflare/agendador/` (`wrangler.toml` + `src/index.js`), versionado
   junto com o app. Duas tentativas por invocação: a primeira costuma pegar o container ainda
   acordando do sleep.

2. **Dois pings por dia (`0 3,15 * * *` UTC), não um por minuto.** Cada ping acorda o container do
   Railway; pingar de minuto em minuto — a premissa do `schedule:run` — manteria o container de pé
   24/7 e anularia a economia do App Sleeping, que é justamente o que torna este deploy viável.

3. **Catch-up em vez de `schedule:run`.** O `schedule:run` só dispara a tarefa se o **minuto atual**
   casar com a expressão cron. Com ping esparso quase nada casaria, e um trigger atrasado em segundos
   já perderia a janela. `App\Support\ExecutarAgendadosCatchUp` faz outra pergunta: *"alguma execução
   ficou devida no intervalo (último tick, agora]?"*. O último tick vive no cache
   (`cron.ultimo_tick`), limitado por uma janela de recuperação (`config('cron.janela_horas')`, 24h).
   Isso **desacopla a frequência do ping da frequência declarada na tarefa** — reduzir os pings atrasa
   a execução, não pula tarefa.

4. **`config/cron.php` é a fonte única do agendamento** (`comando => expressão cron`), lida por dois
   consumidores: o `routes/console.php` (que registra tudo no `Schedule`, para o `schedule:work` do
   docker-compose) e o endpoint HTTP. O preço é abrir mão do açúcar `->daily()`/`->hourly()` para
   essas tarefas: o endpoint precisa da expressão em si para calcular o que ficou devido. O `Schedule`
   fluente continua disponível em `routes/console.php` — mas tarefa que precisa rodar **em produção**
   tem de estar na lista do config.

5. **Execução in-process (`Artisan::call`)**, não subprocesso — que é o que `Event::run()` do
   scheduler faria. Poupa um bootstrap de PHP por tarefa (o `artisan serve` do Railway atende um
   request por vez) e deixa o fluxo testável de ponta a ponta com o SQLite in-memory da suíte. Uma
   tarefa que estoure é logada e não impede as outras; o tick avança mesmo assim, porque as varreduras
   são cumulativas — o ciclo seguinte pega o que sobrou.

6. **Porta fechada por padrão.** `routes/cron.php` é registrado pelo `then:` do `withRouting()`,
   **fora do grupo `web`** (sem sessão, CSRF, cookies ou middlewares de tenant — o `verificar.rede`
   rejeitaria a requisição por falta de usuário). A única credencial é o header `X-Cron-Token`,
   comparado com `hash_equals` pelo middleware `cron.auth`, mais `throttle:12,1`. Duas escolhas
   deliberadas: **fail-closed** (token não configurado ⇒ 404, um ambiente que esqueceu a variável
   nunca expõe o agendador) e **404 em vez de 403** (403 confirmaria a existência da rota para quem
   varre). O segredo vive só nas variáveis do Railway e no `wrangler secret` — nunca no repositório.

## Consequências

### Positivas
- As tarefas agendadas voltam a rodar em produção, sem serviço novo e sem sair dos planos gratuitos:
  2 invocações/dia contra o teto de 100.000/dia do Workers Free.
- App Sleeping continua ligado — o custo do Railway não muda de patamar.
- A frequência do ping virou um parâmetro de operação, não de correção: dá para aumentar ou reduzir
  sem tocar no código da aplicação.
- O endpoint também serve de gatilho manual (`curl`) para forçar a limpeza em qualquer ambiente.

### Negativas / limites
- **Dependência de um terceiro para o relógio.** Se o Worker parar, nada avisa a aplicação; a
  detecção é pelo `wrangler tail` / métricas do Worker.
- **As tarefas rodam dentro de um request.** Com `php artisan serve` (um request por vez), o app fica
  bloqueado durante a varredura — daí os horários de baixo uso. Se incomodar, o caminho é
  `PHP_CLI_SERVER_WORKERS` ou trocar o `artisan serve` por FrankenPHP/php-fpm.
- **Superfície pública nova**, ainda que fechada por token, 404 mudo e throttle.
- Tarefas com granularidade menor que o intervalo entre pings executam **uma vez** no catch-up, não
  N vezes. Adequado para varreduras idempotentes; **não** para tarefas que precisem rodar a cada
  ocorrência (relatório por hora, cobrança recorrente). Uma tarefa assim exige repensar a frequência
  dos pings ou uma fila de verdade.
- Essas tarefas passam a ser declaradas por expressão cron crua, sem o açúcar sintático do `Schedule`.

### Neutras
- A fila continua `sync` em produção. Mandar a exportação de extrato para a fila significaria o
  usuário esperar até o próximo ping pelo arquivo — pior que rodar inline. O desenho assíncrono do
  ADR-0012 continua valendo para o docker-compose e para um deploy com worker de verdade.
- O ambiente de desenvolvimento não muda: o serviço `scheduler` do docker-compose segue rodando
  `schedule:work`, agora lendo as mesmas tarefas do `config/cron.php`.

## Verificação

`tests/Feature/AgendadorHttpTest.php` cobre os dois eixos: a **porta** (404 sem token, com token
errado e com token não configurado; 200 com o token certo) e o **catch-up** (sem tick roda o que
venceu na janela; tick recente roda só a tarefa horária; nada vencido roda nada; o ping seguinte não
repete; o tick é gravado). Um teste de efeito real confirma que uma `Exportacao` além da retenção — e
o arquivo dela no storage — somem depois da chamada.
