---
paths:
  - "app/Modules/Produto/**"
---

# Modulo: Produto

CRUD de produtos com categorias. Catalogo **rede-level** (sem `empresa_id`): produtos e categorias
sao compartilhados entre as empresas da rede. Cadastro e livre (nao depende de plano); apenas a
MOVIMENTACAO de estoque exige plano — ver modulo Estoque.

## Entidades & status
- **`Produto`** (tabela `produtos`) — BaseModel + `SoftDeletes`. Campos: `nome`, `codigo`,
  `codigo_barras`, `descricao`, `categoria_produto_id`, `quantidade` (int, default 0), `valor_custo`,
  `valor_venda` (obrigatorio), `estoque_minimo`, `unidade`, `ativo` (bool, default true),
  `observacoes`. Casts: `valor_venda`/`valor_custo` => `decimal:2`, `ativo` => bool. Relacoes:
  `categoria()` belongsTo CategoriaProduto (FK `categoria_produto_id`, nullOnDelete),
  `movimentos()` hasMany MovimentoEstoque. Nao ha enum de status — so a flag booleana `ativo`.
- **`CategoriaProduto`** (tabela `categorias_produto`) — BaseModel, SEM SoftDeletes. Campos:
  `descricao` (obrigatorio, max 255), `ativo` (bool, default true). NAO tem campo `nome`. Relacao
  `produtos()` hasMany.

## Camadas-chave
- `ProdutoController` — CRUD resource + `buscar()` (AJAX). Injeta `ProdutoService` e `EstoqueService`
  (este usado no `show` para listar movimentos do produto).
- `CategoriaProdutoController` — CRUD resource (sem `show`); param de rota e `{categorias_produto}`.
- `ProdutoService` — `listar()` (filtros q/categoria/ativo/estoque/preco), `buscar()`, `criar()`,
  `atualizar()`, `excluir()` (soft delete). Sem Actions.
- `ProdutoData` / `CategoriaProdutoData` — DTOs unificados (Spatie). CategoriaProdutoData so tem
  `descricao` + `ativo` (default true).
- `SalvarProdutoRequest` / `SalvarCategoriaProdutoRequest` — unificados (`isMethod('post')`).
- `ProdutoPolicy` / `CategoriaProdutoPolicy` — `viewAny`/`view` => `produto.ver`; create =>
  `produto.criar`; update => `produto.editar`; delete => `produto.excluir`. **Categoria reusa as
  permissoes `produto.*`** (nao ha permissao propria de categoria).

## Regras de negocio / gotchas
- Permissoes (PermissaoSeeder): `produto.ver`, `produto.criar`, `produto.editar`, `produto.excluir`.
- Filtro de estoque no `listar()`: `zerado` (qtd <= 0), `baixo` (qtd <= estoque_minimo e > 0),
  `disponivel` (qtd > 0).
- `buscar()` (AJAX) so com `ativo=true` e `q` >= 2 chars; retorna `id, nome, valor_venda, quantidade`.
  Listas de categoria nos forms tambem filtram `ativo=true`.
- Schema vem de 3 migrations: `create_produtos` nasceu com `conta_id`+`valor` (legado);
  `rename_contas_to_redes` (Tenant) renomeou `conta_id`->`rede_id`; `add_campos_produto` renomeou
  `valor`->`valor_venda` e adicionou os demais campos. Nao existe coluna `valor` nem `conta_id` hoje.
- Categorias padrao (Cabelo, Corpo, Rosto, Unhas, Consumiveis, Outros) sao semeadas ao registrar a
  rede.
- `quantidade` so muda via MovimentoEstoque (modulo Estoque) ou venda — nao edite direto na regra.

## Veja tambem
- `.claude/rules/modulos/estoque.md` — movimentacao que altera `produto.quantidade`.
- `.claude/rules/multi-tenant-seguranca.md` — catalogo rede-level x transacional; camadas de auth.
- skill `padroes-projeto` — blueprints de Controller/Service/DTO/Request/Policy.
