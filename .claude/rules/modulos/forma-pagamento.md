---
paths:
  - "app/Modules/FormaPagamento/**"
---

# Modulo: FormaPagamento

Formas de pagamento configuraveis **por empresa** (transacional, `empresa_id`): CRUD livre e nomeado
(ex.: "Credito Cielo"). Cada unidade tem suas maquinas/taxas — nao e catalogo compartilhado da rede.
Cada forma define COMO e ONDE o dinheiro entra (`conta_destino_id`). **Regime "fluxo, nao saldo"
(ADR-0011):** so a forma que cai na **gaveta** (dinheiro -> conta caixa) gera `Lancamento`; toda forma
nao-gaveta (cartao/pix/boleto/crediario/banco) registra **so a `BaixaPagamento`** — sem `Recebivel`,
sem saldo de banco. O `conta_destino_id` vira **rotulo de origem** (Cielo, Rede, banco X). Substitui o
enum fixo antigo. Ver ADR-0009/ADR-0010/ADR-0011, `.claude/rules/modelo-financeiro.md` e `modulos/conta.md`.

## Entidades & status
- **`FormaPagamento`** (tabela `formas_pagamento`) — BaseModel + **EmpresaTrait** + SoftDeletes
  (empresa-level, COM `empresa_id`). Campos: `nome`, `tipo` (enum `TipoFormaPagamento`:
  dinheiro/pix/cartao_debito/cartao_credito/boleto/crediario), `conta_destino_id` (FK nullable ->
  `contas`; conta onde o dinheiro cai — quando null, resolve pela natureza da forma), `ativo`,
  `gera_recebivel` (bool), `dias_liquidacao` (D+N), `taxa_percentual` (plana), `permite_parcelas`,
  `max_parcelas`, `antecipacao_automatica`, `taxa_antecipacao_mensal`. Scope `ativos()`. Relacao
  `conta()` (FK `conta_destino_id`). Metodos `taxaParaParcelas(int)` (usa a faixa; cai na taxa plana se
  nao houver) e `valorLiquido(float, int)`. **Delete so logico** — baixas historicas apontam para o id.
- **`conta_destino_id` e OBRIGATORIO em cartao debito/credito e Pix** (`TipoFormaPagamento::exigeContaDestino()`):
  cada maquineta/canal (Cielo, Rede, Pix direto...) cai numa conta propria — nunca na gaveta. Dinheiro/
  Boleto/Crediario caem no Caixa por natureza (conta opcional, resolvida pelo motor).
- **`FormaPagamentoTaxa`** (tabela `formas_pagamento_taxas`) — BaseModel + **EmpresaTrait** (com
  `rede_id` + `empresa_id`, herdados da forma). Faixa de taxa por nº de parcelas do cartao de
  credito: `parcela_min`, `parcela_max`, `taxa_percentual`. Editada inline no form da forma.

## Camadas-chave
- `FormaPagamentoController` — resource CRUD (except `show`), rota `formas-pagamento` no grupo
  `verificar.plano:financeiro`. Menu em Financeiro (`@can('forma_pagamento.ver')`). `store` resolve a
  empresa via `ContextoEmpresa::resolver() ?? user->empresa_id` (guard: sem empresa unica, avisa para
  escolher no topo) e passa a `criar`.
- `FormaPagamentoService` — `listar` (with `empresa`) / `criar(dados, taxas, empresaId)` / `atualizar`
  / `excluir` + `sincronizarTaxas()` (recria as faixas herdando `empresa_id`)
  + **`semearPadrao(int $redeId, int $empresaId)`** (Dinheiro -> caixa; Pix direto, Debito D+1 1,99% e
  Credito D+30 com faixas -> ligados a **Conta Bancaria** `eh_destino_recebivel_padrao`; Crediario ate
  12x -> caixa). Chamado por **`CriarEmpresaAction`**, `DesenvolvimentoSeeder` e `CriaTenant::criarRede`
  — nesses tres, **as contas sao semeadas ANTES das formas** (o lookup da conta bancaria depende disso;
  se ausente, cai em null e o motor resolve pela natureza).
- `FormaPagamentoData` (DTO) · `SalvarFormaPagamentoRequest` (unificado post/put; valida faixas nao
  sobrepostas e dentro de `max_parcelas` em `withValidator`) · `FormaPagamentoPolicy` (permissoes
  `forma_pagamento.ver/criar/editar/excluir` + `mesmaRedeEEmpresa` via `podeAcessarEmpresa`,
  registrada em `AppServiceProvider`).

## Regras de negocio / gotchas
- A forma e escolhida por **id** em Venda/Pagamento/Despesa (nao mais enum). O `Rule::exists` da
  validacao IGNORA os global scopes — **filtre `rede_id` + `whereIn('empresa_id', empresas_atuais)`
  na mao** (`SalvarBaixaParcelaRequest`, `CriarVendaRequest`, `SalvarDespesaRequest`), senao vaza
  forma de outra rede/empresa. Gate preciso: o `FormaPagamento::findOrFail(...)` do controller ja e
  auto-escopado por empresa (EmpresaTrait), entao forma de outra empresa da 404 no uso.
- `conta_destino_id` tem validacao propria em `SalvarFormaPagamentoRequest`: `regraContaAcessivel`
  (`Rule::exists('contas')` filtra `rede_id` + a **empresa-alvo** da forma — na edicao a da forma, na
  criacao a do contexto — nao so o universo acessivel, senao um Admin com N empresas apontaria a forma
  da empresa A para conta da B); `contaDestinoObrigatoria()` torna o campo **required** em cartao/pix;
  e `validarContaDestinoNaoEhCaixa()` rejeita conta `tipo=Caixa` nesses tipos (cartao/pix nunca caem na
  gaveta). No form, o JS marca required, esconde "Padrao da empresa" e oculta contas Caixa nesses tipos.
- Baixa/parcela guardam `forma_pagamento_id` (FK) + `forma_pagamento_nome` (snapshot p/ historico);
  o `Lancamento` da gaveta guarda so o snapshot (sem FK).
- **PIX e configuravel** (`TipoFormaPagamento::recebivelConfiguravel()`, so p/ Pix): direto ao banco
  ou via maquineta. **ADR-0011:** `gera_recebivel` ainda **roteia a conta destino**, mas NAO produz mais
  `Recebivel` — toda forma nao-gaveta vira **so uma Baixa**. Os campos `dias_liquidacao`,
  `taxa_percentual`/faixas, `antecipacao_automatica`, `taxa_antecipacao_mensal` ficam **so informativos**
  (nao ligam a datas/valores; removidos/enxugados na Fatia 2).
- **Motor de baixa (ADR-0011):** eixo = conta destino e do tipo Caixa? **Gaveta (dinheiro)** -> exige
  caixa + `Lancamento`; **qualquer outra** -> so a Baixa (sem caixa, sem lancamento, sem recebivel). Ver
  `.claude/rules/modulos/caixa.md` e `modulos/conta.md`.
- Na venda, `parcelas_cartao` (nº de parcelas no cartao) e independente de `CondicaoPagamento`; o
  controller forca a-vista quando a forma gera recebivel. JS de `venda-create` popula as formas via
  `window.vendaCreateConfig.formas` e mostra o campo de parcelas quando `permite_parcelas`.
- `TipoFormaPagamento` traz os padroes de comportamento (`geraRecebivelPadrao`,
  `diasLiquidacaoPadrao`, `permiteParcelasPadrao`) — usados no seed e no prefill do form.

## Veja tambem
- ADR-0009 (`docs/ADR/`) — decisao completa.
- `.claude/rules/modelo-financeiro.md` (recebiveis, Titulo+Parcela+Baixa) e `modulos/caixa.md` (motor).
- `.claude/rules/multi-tenant-seguranca.md` (transacional empresa-level; `exists` escopado por
  rede+empresa).
