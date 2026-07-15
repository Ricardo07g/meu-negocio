---
paths:
  - "app/Modules/Caixa/**"
---

# Modulo: Caixa

Caixa diario por empresa: abrir/fechar/reabrir, sangria/reforco, e o motor de baixas
(`darBaixaParcelaPagamento` / `darBaixaParcelaDespesa`) e estorno consumido por Pagamento, Despesa e
Venda. Aqui tambem vivem os models `BaixaPagamento`, `BaixaDespesa` e `MovimentoCaixa`.

## Entidades & status
- `Caixa` (tabela `caixas`): `data`, `saldo_abertura`, `saldo_fechamento`, `status` (enum
  `StatusCaixa`: `Aberto`, `Fechado`), `observacao`, `fechado_em`, `fechado_por`, `usuario_id`.
  BaseModel + EmpresaTrait (isolado por empresa). Relacoes `usuario`, `fechadoPor`, `movimentos`.
- `MovimentoCaixa` (tabela `movimentos_caixa`): **Model direto, NAO BaseModel** (sem rede_id/empresa_id
  proprios — herda contexto via `caixa_id`). `tipo` (enum `TipoMovimentoCaixa`: `Entrada`, `Saida`,
  `Sangria`, `Reforco`), `valor`, `descricao`, `forma_pagamento` nullable, FKs nullable
  `baixa_pagamento_id` / `baixa_despesa_id`.
- `BaixaPagamento` / `BaixaDespesa` (BaseModel + EmpresaTrait): `valor`, `multa`, `juros`, `desconto`,
  `forma_pagamento_id` (FK `formas_pagamento`) + `forma_pagamento_nome` (snapshot), `data`, FK
  `caixa_id` nullable. `valorTotal()` = principal + multa + juros − desconto (liquido que entra/sai do
  caixa). BaixaDespesa tem SoftDeletes; BaixaPagamento nao. **Baixa de cartao tem `caixa_id` NULL**
  (nao entrou na gaveta) e liga-se a N `Recebivel` via `baixa_pagamento_id`.
- `Recebivel` (tabela `recebiveis`; BaseModel + EmpresaTrait + SoftDeletes): a receber do banco/adquirente
  numa venda no cartao. Campos: `forma_pagamento_id`, `baixa_pagamento_id`, `valor_bruto`,
  `taxa_percentual`, `valor_liquido`, `parcela_numero/total`, `data_venda`, `data_prevista`,
  `cancelado_em`. `statusEfetivo()` (enum `StatusRecebivel`) derivado por data (sem job). Scopes
  `ativos/recebidos/previstos`. Ver ADR-0009 e `.claude/rules/modulos/forma-pagamento.md`.

## Camadas-chave
- `CaixaService`:
  - `caixaAberto()` / `caixaDoDia(string $data)` — buscam pela empresa em contexto (global scope da
    EmpresaTrait); NAO filtram empresa_id na mao.
  - `abrir(saldo, data, ?obs)` — lanca `NegocioException` se ja existe caixa NA DATA (regra "1 por
    empresa/dia" e validada em codigo, NAO ha unique no schema). `fechar`, `reabrir` (so caixa
    Fechado; loga activity), `registrarSangria`, `registrarReforco`.
  - `darBaixaParcelaPagamento(..., ?int $parcelasCartao)` -> Entrada; `darBaixaParcelaDespesa(...)` ->
    Saida. Ambos delegam ao template privado `aplicarBaixaParcela(FormaPagamento $forma, bool
    $geraRecebivel, ?int $parcelasCartao, ...)`: valida, cria a Baixa (com `forma_pagamento_id` +
    `forma_pagamento_nome`), soma `valor_pago`, marca Pago, `recalcularStatus()`. **Dois destinos:**
    se `$geraRecebivel` (cartao, so no lado do recebimento — despesa força `false`) NAO exige caixa e
    NAO cria MovimentoCaixa, gera N `Recebivel` via `gerarRecebiveis()` (D+N, liquido de taxa da
    faixa); senao (dinheiro/pix) exige caixa aberto e cria MovimentoCaixa. Tudo em `DB::transaction`.
  - `estornarPagamento(Pagamento)` — parcelas Pendente -> Cancelado; titulo -> `StatusPagamento::Estornado`.
    **Por-baixa:** baixa de cartao (`caixa_id` NULL) cancela seus `Recebivel` (`cancelado_em`), sem
    Saida; baixa em dinheiro/pix gera Saida no caixa de ORIGEM (bloqueia se esse caixa estiver fechado).
- `CaixaController` — `index` (navegacao por `?data=YYYY-MM-DD`, calcula totais/saldo do dia),
  `store` (abrir), `show` (redirect p/ index na data), `fechar`, `reabrir`, `sangria`, `reforco`.
- DTOs `AbrirCaixaData`, `FecharCaixaData`, `MovimentoCaixaData`, `ReabrirCaixaData`. Requests
  homonimos. Policy `CaixaPolicy` (permissoes **`financeiro.ver/criar/editar`** — note o prefixo
  `financeiro`, nao `caixa`).

## Regras de negocio / gotchas
- Saldo do dia (no `index`) = `saldo_abertura + entradas + reforcos − saidas − sangrias`. Sangria
  conta como saida no calculo de saldo.
- Caixa Diario opera em UMA empresa: se ha multiplas em `empresas_atuais` sem contexto, o `index`
  escolhe a primeira silenciosamente (seta `empresa_contexto_atual`) para nao travar a tela.
- Reabrir limpa `saldo_fechamento`/`fechado_em`/`fechado_por` e acrescenta nota de auditoria na
  observacao. So caixas Fechados; senao `NegocioException`.
- `BaixaPagamento`/`BaixaDespesa` tem `empresa_id` NOT NULL — por isso os controllers de
  Pagamento/Despesa envolvem a baixa em `comEmpresaDeCriacao($parcela->empresa_id, ...)`.
- Toda escrita de caixa/baixa vive em transacao no Service; nunca usar `DB::` no controller.

## Veja tambem
- `.claude/rules/modelo-financeiro.md` (fluxo de baixa, estorno, enums).
- `.claude/rules/multi-tenant-seguranca.md` (Caixa = empresa-level; defesa em profundidade na escrita).
