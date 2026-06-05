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
  `forma_pagamento`, `data`, FK `caixa_id` nullable. `valorTotal()` = principal + multa + juros −
  desconto (liquido que entra/sai do caixa). BaixaDespesa tem SoftDeletes; BaixaPagamento nao.

## Camadas-chave
- `CaixaService`:
  - `caixaAberto()` / `caixaDoDia(string $data)` — buscam pela empresa em contexto (global scope da
    EmpresaTrait); NAO filtram empresa_id na mao.
  - `abrir(saldo, data, ?obs)` — lanca `NegocioException` se ja existe caixa NA DATA (regra "1 por
    empresa/dia" e validada em codigo, NAO ha unique no schema). `fechar`, `reabrir` (so caixa
    Fechado; loga activity), `registrarSangria`, `registrarReforco`.
  - `darBaixaParcelaPagamento(...)` -> Entrada; `darBaixaParcelaDespesa(...)` -> Saida. Ambos delegam
    ao template privado `aplicarBaixaParcela()`: valida multa/juros/desconto >= 0, valor <= saldo da
    parcela, exige caixa aberto, cria Baixa + MovimentoCaixa, soma `valor_pago` na parcela, marca
    parcela Pago se quitada, e chama `recalcularStatus()` do titulo. Tudo em `DB::transaction`.
  - `estornarPagamento(Pagamento)` — parcelas Pendente -> Cancelado; se houve valor recebido EXIGE
    caixa aberto e gera Saida com `valorPago()`; titulo -> `StatusPagamento::Estornado`.
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
