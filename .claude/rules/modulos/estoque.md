---
paths:
  - "app/Modules/Estoque/**"
---

# Modulo: Estoque

Movimentacoes de estoque (entrada, saida, ajuste) que alteram `produto.quantidade`. **Transacional —
empresa-level**: `MovimentoEstoque` tem `empresa_id` e e isolado por empresa (o Produto em si e
rede-level). Acesso a movimentacao exige plano via middleware `verificar.plano:estoque`.

## Entidades & status
- **`MovimentoEstoque`** (tabela `movimentos_estoque`) — BaseModel + `EmpresaTrait` +
  `RegistraAtividade`. **Sem SoftDeletes** (append-only / movimentos permanentes; nao ha update nem
  delete). Campos: `rede_id`, `empresa_id`, `produto_id`, `tipo`, `quantidade` (int). Cast:
  `tipo` => `TipoMovimentoEstoque`. Relacao `produto()` belongsTo. So tem `created_at`/`updated_at`,
  sem deleted_at.
- **`TipoMovimentoEstoque`** (`app/Enums`, string): `Entrada='entrada'`, `Saida='saida'`,
  `Ajuste='ajuste'`.

## Camadas-chave
- `MovimentoEstoqueController` — apenas `index`, `create`, `store` (resource `->only(...)`). Usa
  `DefineEmpresaDeCriacao` + `TratamentoErros`; o `store` envolve a escrita em
  `comEmpresaDeCriacao($empresaId, fn ...)`.
- `EstoqueService` — `registrarMovimento()` (em `DB::transaction`) e `listarMovimentos()`
  (filtros q/produto_id/tipo/periodo). Service e tambem chamado pelo ProdutoController (show) e pelo
  VendaService.
- `RegistrarMovimentoData` — DTO com `produto_id`, `tipo` (enum), `quantidade`.
- `RegistrarMovimentoRequest` — valida `produto_id` exists, `tipo` Rule::enum, `quantidade` min:1,
  e `empresa_id` nullable + `Rule::in(session('empresas_atuais'))` (ME-010 v3: empresa vem do
  contexto; ausente e aceito). `authorize()` => `movimento_estoque.criar`.
- `MovimentoEstoquePolicy` — so `viewAny` (`movimento_estoque.ver`) e `create`
  (`movimento_estoque.criar`). Nao ha update/delete/view individual.

## Regras de negocio / gotchas
- Efeito no estoque em `registrarMovimento()`: Entrada => `increment('quantidade', n)`; Saida =>
  `decrement('quantidade', n)`; Ajuste => `update(['quantidade' => n])` (DEFINE valor absoluto, nao
  soma). Tudo em transacao.
- Permissoes (PermissaoSeeder): apenas `movimento_estoque.ver` e `movimento_estoque.criar` — NAO
  existem `.editar`/`.excluir` (movimentos sao imutaveis).
- Venda de produto cria automaticamente um MovimentoEstoque tipo `Saida` (via VendaService); cancelar
  a venda devolve o estoque.
- `Saida`/`Ajuste` nao tem trava de quantidade negativa no Service — a regra confia na entrada do
  formulario; quantidade pode ficar negativa se a saida exceder o disponivel.
- Schema (1 migration): nasceu com `conta_id` (renomeado para `rede_id` pela migration
  `rename_contas_to_redes` do Tenant) + `empresa_id` + `produto_id`. Indices `[rede_id, empresa_id]`
  e `produto_id`.

## Veja tambem
- `.claude/rules/modulos/produto.md` — Produto (rede-level) cujo `quantidade` e alterado aqui.
- `.claude/rules/multi-tenant-seguranca.md` — EmpresaTrait, `comEmpresaDeCriacao`, contexto ME-010,
  plano (`verificar.plano:estoque`).
- skill `padroes-projeto`.
