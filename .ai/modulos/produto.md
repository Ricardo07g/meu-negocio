# Modulo: Produto

CRUD de produtos com categorias. Independente do plano (cadastro livre, movimentacao requer plano).

## Localizacao

`app/Modules/Produto/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Produto.php, CategoriaProduto.php |
| Controllers | ProdutoController.php, CategoriaProdutoController.php |
| Services | ProdutoService.php |
| DTOs | CriarProdutoData, AtualizarProdutoData, CriarCategoriaProdutoData, AtualizarCategoriaProdutoData |
| Requests | CriarProdutoRequest, AtualizarProdutoRequest, CriarCategoriaProdutoRequest, AtualizarCategoriaProdutoRequest |
| Policies | ProdutoPolicy.php, CategoriaProdutoPolicy.php |
| Views | index, create, edit, show + categorias/ |
| Migrations | create_produtos, create_categorias_produto, add_campos_produto |

## Models

### Produto
- Tabela: `produtos`
- Traits: PertenceARede, SoftDeletes
- Fillable: rede_id, nome, codigo, codigo_barras, descricao, categoria_produto_id, quantidade, valor_custo, valor_venda, estoque_minimo, unidade, ativo, observacoes
- Casts: valor_venda/valor_custo → decimal:2, ativo → boolean
- Relacoes: categoria (belongsTo CategoriaProduto), movimentos (hasMany MovimentoEstoque)

### CategoriaProduto
- Tabela: `categorias_produto`
- Traits: PertenceARede
- Fillable: rede_id, nome, descricao
- Relacoes: produtos (hasMany)

## Categorias padrao

Criadas automaticamente no registro da rede (RedeService):
Cabelo, Corpo, Rosto, Unhas, Consumiveis, Outros

## Schema: produtos

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| nome | string(200) | — |
| codigo | string(50) | null |
| codigo_barras | string(50) | null |
| descricao | text | null |
| categoria_produto_id | FK categorias_produto | null |
| quantidade | int | 0 |
| valor_custo | decimal(10,2) | null |
| valor_venda | decimal(10,2) | — |
| estoque_minimo | int | null |
| unidade | string(20) | null |
| ativo | bool | true |
| observacoes | text | null |
| deleted_at | timestamp | null |

## Schema: categorias_produto

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes (cascade) | — |
| nome | string(100) | — |
| descricao | string(255) | null |

## Notas

- Cadastro de produtos e livre (nao depende de plano)
- Movimentacao de estoque requer `verificar.plano:estoque`
- Produto usa apenas `PertenceARede` (sem PertenceAEmpresa)
