# Modulo: Venda

Gerencia 3 tipos de venda: avulso (servico), pacote (servico com sessoes), produto.

## Localizacao

`app/Modules/Venda/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | VendaPacote.php, VendaProduto.php |
| Controllers | VendaController.php |
| Services | VendaService.php |
| Actions | VenderPacoteAction.php |
| DTOs | VenderPacoteData.php |
| Requests | CriarVendaRequest.php |
| Policies | VendaPacotePolicy.php |
| Views | index, create, show-avulso, show-pacote, show-produto |
| Migrations | create_vendas_pacote, create_vendas_produto |

## Tipos de venda

### 1. Venda Avulso (servico unico)
- Cria 1 Agendamento + 1 Pagamento
- Nao usa VendaPacote
- Agendamento.venda_pacote_id = null

### 2. Venda Pacote (multiplas sessoes)
- Cria 1 VendaPacote + N Agendamentos + 1 Pagamento
- Cada agendamento referencia vendaPacote
- Controla sessoes realizadas vs pendentes

### 3. Venda Produto
- Cria 1 VendaProduto + 1 Pagamento
- Baixa automatica no estoque (decrementa produto.quantidade)
- Cria MovimentoEstoque tipo saida

## Models

### VendaPacote
- Tabela: `vendas_pacote`
- Traits: PertenceARede, PertenceAEmpresa, SoftDeletes
- Fillable: rede_id, empresa_id, cliente_id, servico_id, atendente_id, valor_total, qtd_sessoes, status
- Casts: valor_total → decimal:2, status → StatusVendaPacote
- Metodos: sessoesRealizadas() (count finalizados), sessoesPendentes() (count agendados+confirmados)
- Relacoes: cliente, servico, atendente, agendamentos

### VendaProduto
- Tabela: `vendas_produto`
- Traits: PertenceARede, PertenceAEmpresa, SoftDeletes
- Fillable: rede_id, empresa_id, cliente_id, produto_id, quantidade, valor_total
- Casts: valor_total → decimal:2
- Relacoes: cliente, produto

## VendaService — regras de negocio

### criarAvulso()
1. Cria agendamento via CriarAgendamentoAction
2. Cria pagamento com valor, forma, status informados
3. Se pagamento "pago" e caixa aberto → registra entrada no caixa

### criarPacote()
1. Cria VendaPacote via VenderPacoteAction (que cria N agendamentos)
2. Cria pagamento vinculado ao pacote
3. Se pagamento "pago" e caixa aberto → registra entrada no caixa

### criarVendaProduto()
1. Cria VendaProduto
2. Decrementa produto.quantidade
3. Cria MovimentoEstoque tipo saida
4. Cria pagamento
5. Se pagamento "pago" e caixa aberto → registra entrada no caixa

### cancelarPacote()
- Cancela todos agendamentos com status agendado/confirmado
- (pagamento nao e estornado automaticamente — diferente do avulso)

### listar()
Agrega 3 tipos de venda numa lista unica, ordenada por data de criacao.

## VenderPacoteAction

1. Recebe lista de datas/horarios
2. Para cada data: cria agendamento com duracao do servico
3. Verifica conflito para CADA sessao
4. Se qualquer sessao tem conflito: lanca ConflitoAgendamentoException com lista de datas
5. qtd_sessoes = count de datas

## VendaController

- `create()` verifica se caixa esta aberto antes de mostrar formulario
- `store()` roteia entre produto, pacote e avulso baseado no tipo informado
