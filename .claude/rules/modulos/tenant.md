---
paths:
  - "app/Modules/Tenant/**"
---

# Modulo: Tenant

Nucleo do multi-tenant: gerencia `Rede` (tenant raiz), `Empresa` (N por rede, **cada uma e uma
licenca**), `Plano` (preco por licenca + assentos + feature flags) e a Assinatura (tela "Minha
Assinatura", `Fatura`s mensais internas). Sem gateway de pagamento — cobranca simulada.

> **O plano e da EMPRESA, nao da rede** (ADR-0013). `redes.plano_id` nao existe mais. Nunca escreva
> `$rede->plano`: use `App\Support\PlanoVigente` (ver abaixo).

## Entidades & status
- **Rede** (`redes`): tenant raiz, so agrupa licencas. `Model` direto (NAO BaseModel) + SoftDeletes. Fillable `nome, status`. Cast `status => App\Enums\StatusRede` (`Ativa`, `Inativa`, `Suspensa`, `Cancelada`). Relacoes: `empresas`/`usuarios` (hasMany). **Sem relacao `plano`.**
- **Empresa** (`empresas`): a licenca. BaseModel + SoftDeletes. Fillable `rede_id, plano_id, trial_expira_em, nome, documento, telefone, email`. Cast `trial_expira_em => date`. **Sem `empresa_id`/EmpresaTrait** (a propria empresa). `plano()` (belongsTo), `usuarios()` = pivot `empresa_usuario` (N:N, fonte de verdade de acesso), `usuariosDefault()` = `usuarios.empresa_id` (compat). Tambem hasMany clientes, servicos, agendamentos, pagamentos, despesas, produtos.
  - `emTrial()` / `diasRestantesTrial()` — o trial vale ate o **fim** do dia `trial_expira_em`.
  - `trialVencido()` — ja teve teste e ele acabou. Serve so para o **texto** da tela ("terminou em dd/mm" vs "esta no Grátis"): a data e nula tanto em quem nunca testou quanto em quem foi rebaixado pela versao antiga do `EncerrarTrialAction`. **Nao use para elegibilidade.**
  - `podeRenovarTrial()` — `! emTrial()` **e** plano atual e o Gratis. **Quem esta no Gratis pode testar o Pro**, com ou sem historico: exigir teste anterior prendia no Gratis justamente as contas rebaixadas antes da feature existir. A guarda que sobra e a licenca paga.
  - `contarUsuarios()` — assentos ocupados = pivot ∪ `usuariosDefault` (o Admin do registro so entra pelo segundo caminho; contar so o pivot o deixaria de fora do limite).
  - `Empresa::DIAS_DE_TRIAL` = 15 — **uma constante so**, vale no registro e em cada renovacao pedida pelo Admin.
- **Plano** (`planos`): `Model` direto (sem RedeTrait — e global). Fillable `slug, nome, preco_por_licenca, descricao, max_usuarios, tem_estoque, tem_financeiro`. Casts `preco_por_licenca => decimal:2`, flags boolean. Constantes `Plano::GRATIS` / `Plano::PRO` — **busque sempre por `slug`**, `nome` e so rotulo. `empresas()` (hasMany). **Nao existe `max_empresas` nem `0 = ilimitado`**: todo limite e finito.
- **Fatura** (`faturas`): BaseModel + SoftDeletes (filtra por `rede_id`). Fillable `rede_id, plano_id, referencia, valor, vencimento, pago_em, status`. `status` e **string** (`em_aberto`, `paga`, `vencida`, `cancelada`) — NAO ha enum `StatusFatura` ainda. `referencia` = `YYYY-MM`. Unique `(rede_id, referencia)` => no maximo 1 fatura por mes por rede.

## Os dois planos
| | Grátis (`gratis`) | Pro (`pro`) |
|---|---|---|
| preco | R$ 0 | R$ 79,90 por licenca/mes |
| `max_usuarios` | 2 | 15 |
| `tem_estoque` / `tem_financeiro` | ✘ | ✔ |

O Gratis vale para **uma unica unidade por rede** (guarda em `CriarEmpresaAction` e em
`TransicionarPlanoAction`).

## Camadas-chave
- **`App\Support\PlanoVigente`** — resolve a licenca da empresa em contexto:
  `ContextoEmpresa::resolver() ?? $usuario->empresa_id`. Metodos `empresaId()`, `empresa()`,
  `resolver()`. Usado por `VerificarPlano`, `UsuarioController`, `AssinaturaController` e pelos
  gates da sidebar (`layouts/app.blade.php` monta `$empresaVigente`/`$planoVigente` uma unica vez
  no topo do body).
- `CriarEmpresaAction::executar(Rede, EmpresaData, ?Plano): Empresa` — contrata a licenca (Pro por
  padrao), aplica a regra do Gratis unico, semeia contas + formas de pagamento e define o trial
  **so na primeira unidade da rede**. E a costura que o painel de superusuario vai consumir.
- `ValidarPlanoAction::executar(Empresa, string $recurso)` — assentos/flags; lanca
  `PlanoLimiteException`. Recursos: `usuario`, `estoque`, `financeiro` (**nao existe mais `empresa`**).
- `TransicionarPlanoAction::executar(Empresa, Plano): Fatura` — troca a licenca de UMA unidade,
  encerra o trial, valida assentos e Gratis-unico, e ajusta a fatura do mes (soma as demais licencas
  cobraveis + rateia por dias so a que mudou). Tudo em `DB::transaction`.
- `EncerrarTrialAction::executar(?Empresa): int` — rebaixa para o Gratis as licencas vencidas,
  **preservando `trial_expira_em`** (a data e o registro de que a unidade ja testou). Idempotente
  pelo plano de destino: quem ja esta no Gratis sai da query. Chamada pelo comando
  `assinaturas:expirar-trial` (agendado `daily()`) e, defensivamente, por `VerificarEmpresa`
  (1x por sessao por dia).
- `RenovarTrialAction::executar(Empresa): Empresa` — reabre o teste por
  `Empresa::DIAS_DE_TRIAL` dias (volta ao Pro). Exige `podeRenovarTrial()`; recusa com
  `NegocioException` quem nunca testou, quem esta em teste vigente e quem esta em licenca paga.
  **Nao mexe na fatura** — Gratis e teste custam R$ 0. Renovacoes sao ilimitadas de proposito
  (cortesia enquanto nao ha gateway).
- `RedeService::criar(CriarRedeData, UsuarioData): Rede` — registro: cria rede, primeira empresa
  (Pro em trial de 15 dias, via Action), usuario Admin e o seed **enxuto**: 1 categoria `Geral`,
  1 `Produto exemplo`, 1 `Servico exemplo`, 1 `Cliente exemplo`. Tudo em transacao.
- `EmpresaService` — `listar` (eager-load `plano`) / `buscar` / `criar` / `atualizar` / `excluir`.
  `criar` e `excluir` nao tem rota: existem para o painel de superusuario.
- `AssinaturaController` — `index()` (licencas da rede + fatura consolidada + historico),
  `transicionar()` (POST, exige `empresa_id` + `plano_id`) e `renovarTeste()` (POST, exige
  `empresa_id`).
- `EmpresaController` — resource **`only(['index','edit','update'])`**. Contratar e cancelar
  unidade sao atos do operador, nao do tenant.
- `FaturaPolicy`: `viewAny`, `transicionar` e `renovarTrial` — **todas `hasRole('Admin')`**.
  Assinatura e assunto do dono: preco, fatura e prazo de teste nao aparecem para os demais perfis.
  A Policy e a fonte unica — o item de menu e o aviso de teste no layout usam
  `@can('viewAny', Fatura::class)`, **nao** a permissao `plano.ver` (que deixou de governar isso).
  `EmpresaPolicy`: permissoes `empresa.ver/criar/editar/excluir` + checa `rede_id` e
  `podeAcessarEmpresa` em update/delete.
- Requests: `TransicionarPlanoRequest` (`empresa_id` + `plano_id`), `RenovarTrialRequest`
  (`empresa_id`), `SalvarEmpresaRequest` (unificado post/put). DTOs: `EmpresaData`, `CriarRedeData`
  (so `nome`).

## Regras de negocio / gotchas
- **Trial e estado da licenca, nao um terceiro plano.** Durante o teste a unidade ESTA no Pro de
  verdade — por isso nada muda em feature flags, middleware ou gates de menu. So a primeira unidade
  da rede ganha trial; unidade contratada depois nasce paga.
- **Teste vencido nao e beco sem saida.** Sem gateway, o Admin tem duas portas na tela de
  assinatura: contratar o Pro ou renovar o teste por mais 15 dias (ilimitadamente). Gotcha ao
  montar cenario de teste: "Pro com trial vencido" nao e estado estavel por HTTP — o middleware
  `VerificarEmpresa` rebaixa a unidade antes do controller. Para exercitar a guarda de licenca
  paga, chame a `RenovarTrialAction` direto.
- **O rebaixamento acontece mesmo com a unidade acima dos limites do Gratis.** Nada e apagado:
  `ValidarPlanoAction` so impede criar mais.
- **Upgrade Gratis -> Pro e self-service do Admin.** Downgrade e contratacao de unidade nova sao do
  operador (fora da UI). Trocar para o mesmo plano lanca `NegocioException`.
- **Pro-rata (ADR-0007, adaptado)**: `valor = outras_licencas_cobraveis +
  (preco_antigo*dias_decorridos + preco_novo*dias_restantes) / dias_no_mes`, com
  `dias_decorridos = hoje->day - 1`. Recai sobre a fatura `em_aberto` do mes (UPDATE no mesmo
  registro — respeita o unique); se nao existir, cria com vencimento no fim do mes.
- **Um `GET` nao escreve no banco.** O antigo `garantirHistoricoFaturas()` fabricava meses
  retroativos com `rand()` ao abrir a tela — foi removido; dado ficticio vive no
  `DesenvolvimentoSeeder`.
- **Rede sem licencas cobraveis nao tem fatura**: a tela mostra "Sem cobranca" em vez de R$ 0.
- `faturas.plano_id` aponta para o plano da ultima transicao — perde sentido numa rede com licencas
  diferentes. Sai quando a fatura ganhar itens por unidade (Fase 2).
- Plano e global (sem RedeTrait): nunca aplicar tenancy nele. Rede usa `Model` direto pois e o
  proprio tenant — escopo seria recursivo.
- **Migration gotcha:** `redes` nasceu como `contas` (`rename_contas_to_redes`) e o MySQL preserva o
  nome da constraint ao renomear a tabela — a FK era `contas_plano_id_foreign`, nao
  `redes_plano_id_foreign`. Ao mexer em FK de `redes`, descubra o nome via
  `Schema::getForeignKeys()` em vez de assumir a convencao.

## Veja tambem
- `docs/ADR/0013-licenca-por-empresa.md` — a decisao completa (plano por empresa, 2 planos, trial).
- `docs/ADR/0007-assinatura-faturamento.md` — parcialmente substituido pelo 0013.
- `.claude/rules/multi-tenant-seguranca.md` — RedeTrait/EmpresaTrait, middlewares (`verificar.rede`,
  `verificar.empresa`, `verificar.plano:{modulo}`), pivot `empresa_usuario`, sessao de contexto,
  `PlanoLimiteException`/`NegocioException`.
