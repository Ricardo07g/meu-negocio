---
paths:
  - "app/Modules/FormaPagamento/**"
---

# Modulo: FormaPagamento

Catalogo **rede-level** de formas de pagamento configuraveis (CRUD livre e nomeado, ex.: "Credito
Cielo"). Cada forma define COMO o dinheiro entra: na gaveta do caixa (dinheiro/pix) ou como recebivel
do adquirente (cartao — D+N, liquido de taxa). Substitui o enum fixo antigo. Ver ADR-0009 e
`.claude/rules/modelo-financeiro.md`.

## Entidades & status
- **`FormaPagamento`** (tabela `formas_pagamento`) — BaseModel + SoftDeletes (rede-level, SEM
  `empresa_id`). Campos: `nome`, `tipo` (enum `TipoFormaPagamento`:
  dinheiro/pix/cartao_debito/cartao_credito/boleto), `ativo`, `gera_recebivel` (bool),
  `dias_liquidacao` (D+N), `taxa_percentual` (plana), `permite_parcelas`, `max_parcelas`. Scope
  `ativos()`. Metodos `taxaParaParcelas(int)` (usa a faixa; cai na taxa plana se nao houver) e
  `valorLiquido(float, int)`. **Delete so logico** — baixas historicas apontam para o id.
- **`FormaPagamentoTaxa`** (tabela `formas_pagamento_taxas`) — BaseModel (com `rede_id`). Faixa de
  taxa por nº de parcelas do cartao de credito: `parcela_min`, `parcela_max`, `taxa_percentual`.
  Editada inline no form da forma.

## Camadas-chave
- `FormaPagamentoController` — resource CRUD (except `show`), rota `formas-pagamento` no grupo
  `verificar.plano:financeiro`. Menu em Financeiro (`@can('forma_pagamento.ver')`).
- `FormaPagamentoService` — `listar/criar/atualizar/excluir` + `sincronizarTaxas()` (recria as faixas)
  + **`semearPadrao(int $redeId)`** (Dinheiro/Pix caixa; Debito D+1 1,99%; Credito D+30 com faixas
  1x/2-6x/7-12x). Chamado por `RedeService::criar` (registro), `DesenvolvimentoSeeder` e pelo helper
  de teste `CriaTenant::criarRede`.
- `FormaPagamentoData` (DTO) · `SalvarFormaPagamentoRequest` (unificado post/put; valida faixas nao
  sobrepostas e dentro de `max_parcelas` em `withValidator`) · `FormaPagamentoPolicy` (permissoes
  `forma_pagamento.ver/criar/editar/excluir`, registrada em `AppServiceProvider`).

## Regras de negocio / gotchas
- A forma e escolhida por **id** em Venda/Pagamento/Despesa (nao mais enum). O `Rule::exists` da
  validacao IGNORA o global scope de rede — **filtre `rede_id` na mao** (`SalvarBaixaParcelaRequest`,
  `CriarVendaRequest`, `SalvarDespesaRequest`), senao vaza forma de outra rede.
- Baixa/parcela guardam `forma_pagamento_id` (FK) + `forma_pagamento_nome` (snapshot p/ historico);
  `movimentos_caixa` guarda so o snapshot (sem FK — cartao nunca gera movimento).
- `gera_recebivel = true` (cartao) muda o motor de baixa: sem caixa, sem MovimentoCaixa, gera
  `Recebivel`. Ver `.claude/rules/modulos/caixa.md`.
- Na venda, `parcelas_cartao` (nº de parcelas no cartao) e independente de `CondicaoPagamento`; o
  controller forca a-vista quando a forma gera recebivel. JS de `venda-create` popula as formas via
  `window.vendaCreateConfig.formas` e mostra o campo de parcelas quando `permite_parcelas`.
- `TipoFormaPagamento` traz os padroes de comportamento (`geraRecebivelPadrao`,
  `diasLiquidacaoPadrao`, `permiteParcelasPadrao`) — usados no seed e no prefill do form.

## Veja tambem
- ADR-0009 (`docs/ADR/`) — decisao completa.
- `.claude/rules/modelo-financeiro.md` (recebiveis, Titulo+Parcela+Baixa) e `modulos/caixa.md` (motor).
- `.claude/rules/multi-tenant-seguranca.md` (catalogo rede-level; `exists` escopado por rede).
