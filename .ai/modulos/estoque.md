# Modulo: Estoque

Controle de movimentacoes de estoque (entrada, saida, ajuste).

## Localizacao

`app/Modules/Estoque/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | MovimentoEstoque.php |
| Controllers | MovimentoEstoqueController.php |
| Services | EstoqueService.php |
| DTOs | RegistrarMovimentoData.php |
| Requests | RegistrarMovimentoRequest.php |
| Policies | MovimentoEstoquePolicy.php |
| Views | movimentos (index), criar-movimento (create) |
| Migrations | create_movimentos_estoque_table |

## Model: MovimentoEstoque

- Tabela: `movimentos_estoque`
- Traits: PertenceARede, PertenceAEmpresa
- Fillable: rede_id, empresa_id, produto_id, tipo, quantidade
- Casts: tipo → TipoMovimentoEstoque
- Relacoes: produto (belongsTo)

## Tipos de movimento (TipoMovimentoEstoque enum)

| Valor | Efeito no estoque |
|-------|-------------------|
| Entrada | Incrementa produto.quantidade |
| Saida | Decrementa produto.quantidade |
| Ajuste | Define produto.quantidade para valor exato |

## EstoqueService — regras de negocio

### registrarMovimento()
1. Cria MovimentoEstoque
2. Atualiza produto.quantidade conforme tipo:
   - Entrada: `quantidade += valor`
   - Saida: `quantidade -= valor`
   - Ajuste: `quantidade = valor`
3. Tudo em transacao

### listarMovimentos()
Lista movimentos, filtravel por produto_id.

## Schema: movimentos_estoque

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| empresa_id | FK empresas | — |
| produto_id | FK produtos | — |
| tipo | string(20) | — |
| quantidade | int | — |

Indices: `[rede_id, empresa_id]`, `produto_id`

## Notas

- Acesso requer `verificar.plano:estoque` (middleware)
- Venda de produto cria MovimentoEstoque tipo Saida automaticamente (via VendaService)
- Nao tem soft deletes (movimentos sao permanentes)
