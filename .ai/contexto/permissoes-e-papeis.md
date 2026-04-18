# Permissoes e Papeis

## Sistema

Usa `spatie/laravel-permission` ^7.2 com guard `web`.

## Papeis

| Papel | Descricao |
|-------|-----------|
| Dono | Todas as permissoes (dono da rede) |
| Admin | Quase todas — nao pode alterar plano |
| Gerente | Clientes, servicos, agenda, financeiro |
| Profissional | Agenda (ver/criar), cliente (ver), servico (ver) |
| Recepcao | Clientes, agendamentos, pagamentos |
| Financeiro | Financeiro, pagamentos, despesas |
| Estoque | Produtos, movimentos de estoque |
| Visualizador | Somente ver (todas as areas) |

Enum: `app/Enums/PapelEnum.php` (sem "Dono" — Dono e o criador da rede)

## Permissoes (formato: recurso.acao)

### Rede
- rede.ver, rede.editar, rede.configurar, rede.cobranca

### Empresa
- empresa.ver, empresa.criar, empresa.editar, empresa.excluir

### Usuario
- usuario.ver, usuario.criar, usuario.editar, usuario.excluir

### Cliente
- cliente.ver, cliente.criar, cliente.editar, cliente.excluir

### Servico
- servico.ver, servico.criar, servico.editar, servico.excluir

### Profissional
- profissional.ver, profissional.criar, profissional.editar, profissional.excluir

### Agendamento
- agendamento.ver, agendamento.criar, agendamento.editar, agendamento.cancelar, agendamento.excluir

### Financeiro
- financeiro.ver, financeiro.criar, financeiro.editar, financeiro.excluir, financeiro.relatorio

### Pagamento
- pagamento.ver, pagamento.criar, pagamento.editar, pagamento.excluir

### Despesa
- despesa.ver, despesa.criar, despesa.editar, despesa.excluir

### Estoque
- estoque.ver, estoque.criar, estoque.editar, estoque.excluir

### Produto
- produto.ver, produto.criar, produto.editar, produto.excluir

### Movimento de estoque
- movimento_estoque.ver, movimento_estoque.criar

### Plano
- plano.ver, plano.alterar

## Matriz papel x permissoes

| Permissao | Dono | Admin | Gerente | Profissional | Recepcao | Financeiro | Estoque | Visualizador |
|-----------|------|-------|---------|-------------|----------|------------|---------|-------------|
| rede.* | sim | nao plano | nao | nao | nao | nao | nao | nao |
| empresa.* | sim | sim | nao | nao | nao | nao | nao | nao |
| usuario.* | sim | sim | nao | nao | nao | nao | nao | nao |
| cliente.* | sim | sim | sim | ver | sim | nao | nao | ver |
| servico.* | sim | sim | sim | ver | nao | nao | nao | ver |
| agendamento.* | sim | sim | sim | ver/criar | sim | nao | nao | ver |
| financeiro.* | sim | sim | sim | nao | nao | sim | nao | ver |
| pagamento.* | sim | sim | sim | nao | sim | sim | nao | ver |
| despesa.* | sim | sim | nao | nao | nao | sim | nao | ver |
| produto.* | sim | sim | nao | nao | nao | nao | sim | ver |
| estoque.* | sim | sim | nao | nao | nao | nao | sim | ver |
| plano.* | sim | nao | nao | nao | nao | nao | nao | nao |

## Policies obrigatorias

Cada model deve ter Policy. Policies existentes:
- RedePolicy, EmpresaPolicy, PlanoPolicy
- UsuarioPolicy, ClientePolicy
- ServicoPolicy, AgendamentoPolicy
- PagamentoPolicy, DespesaPolicy
- ProdutoPolicy, CategoriaProdutoPolicy
- MovimentoEstoquePolicy, CaixaPolicy
- VendaPacotePolicy, PapelPolicy

## Validacao

Toda action deve validar: permissao + tenant + empresa + plano.
Nunca executar sem validar.
