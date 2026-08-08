# ADR-0014 — O caixa do dia mostra o fluxo da loja, não o razão da gaveta

## Status

Aceito — agosto/2026. **Complementa o ADR-0011** (que decidiu *o que* o sistema registra: fluxo, não saldo) decidindo *o que a tela do Caixa apresenta*. Não altera o modelo de dados nem o motor de baixas.

## Contexto

O ADR-0011 acertou o registro, mas a tela do Caixa Diário continuou mostrando **duas listas**, e nenhuma delas era "o movimento financeiro do meu dia":

1. **`Recebimentos do dia por forma`** — só o que **entrou**, agregado por forma (`ResumoDiaService`).
2. **`Movimentos da gaveta`** — `$caixa->lancamentos`, isto é, o **razão da conta-caixa**.

Três problemas concretos apareceram no uso real:

1. **Duplicação com o extrato da Conta.** A tabela `Movimentos da gaveta` era literalmente a mesma tabela (Data/Tipo/Descrição/Forma/Valor), sobre a mesma fonte, que o card `Lançamentos` de `/contas/{conta-caixa}/extrato`. Duas telas mantendo o mesmo componente.

2. **É o eixo errado para a pergunta do lojista.** Como só dinheiro em espécie gera `Lancamento` (ADR-0011), uma venda no cartão **não aparecia** nessa tabela. O relato que originou este ADR foi exatamente isso: *"fiz uma venda no cartão de crédito em 2x, o caixa mostra o recebimento na forma, mas não mostra entrada nem movimento nenhum — acho que isso não está correto"*. Não estava errado no dado; estava errado na representação. Pior: quando **não havia caixa aberto** no dia — o caso normal de quem só vende no cartão — a metade de baixo da tela era só *"Nenhum caixa registrado neste dia"*, e as vendas do dia ficavam invisíveis.

3. **Faltava metade do dia.** **Despesa paga não aparecia em lugar nenhum** dessa tela. Paga em dinheiro, virava um débito genérico "Saída" na gaveta, sem dizer qual conta era; paga no pix, não existia. O lojista não tinha onde ver "o que entrou e o que saiu hoje".

## Decisão

**A tela do Caixa apresenta o eixo do FLUXO — o dia da loja. O razão da conta-caixa fica no extrato da Conta.**

1. **Uma timeline única, `Movimentações do dia`** (`MovimentacaoDiaService::doDia`), com uma linha por evento que movimentou dinheiro, classificada por natureza no enum `TipoMovimentacaoDia`: `Venda`, `Recebimento` (parcela de título a prazo), `Despesa`, `Estorno`, `Sangria`, `Reforco`. Cada linha mostra hora, tipo, descrição com a origem (cliente/fornecedor, venda/serviço/pacote, "Parcela 2/3"), a forma com o parcelamento (`BaixaPagamento::rotuloForma()`), a **conta de destino/origem** e o valor com sinal.

2. **Partição disjunta das fontes** — é aqui que se erra. Um recebimento em dinheiro existe **duas vezes** no banco: como `BaixaPagamento` **e** como `Lancamento` de crédito. Então:

   | Linha | Fonte | Filtro |
   |---|---|---|
   | Venda / Recebimento | `BaixaPagamento` | `data` no dia |
   | Estorno | `BaixaPagamento` | `estornado_em` no dia |
   | Despesa paga | `BaixaDespesa` | `data` no dia |
   | Sangria / Reforço | `Lancamento` | `categoria IN (sangria, reforco)` |

   Do `Lancamento` entram **apenas** `sangria` e `reforco` — os únicos eventos nativos da gaveta, sem baixa por trás. As categorias `movimento` e `estorno` são o espelho contábil das três primeiras linhas e ficam **fora**, sob pena de dobrar o dia. Travado por teste (`MovimentacaoDiaTest::test_recebimento_em_dinheiro_nao_duplica_na_timeline`).

3. **Os cards do topo são do negócio, não da gaveta**: `Entradas do dia`, `Saídas do dia`, `Resultado do dia`. **Sangria e reforço não entram no resultado** (`TipoMovimentacaoDia::contaNoResultado()`): é dinheiro trocando de lugar entre a gaveta e o bolso/banco, não receita nem despesa. Isso remove a justaposição que gerava a leitura de erro ("Cartão R$ 30" ao lado de "Entradas R$ 0,00").

4. **A timeline e os cards ficam fora do `@if($caixa)`.** Cartão e pix não exigem gaveta; um dia sem caixa aberto passa a mostrar o dia normalmente, com um aviso enxuto de que o dinheiro físico não foi conferido.

5. **A gaveta encolhe para o que ela é: reconciliação.** Um bloco `Fechamento da gaveta` com `Abertura + Entradas em dinheiro − Saídas em dinheiro = Deve estar na gaveta`, os botões de sangria/reforço/fechar e um link para o extrato da conta Caixa. O saldo **não muda de fonte**: segue vindo só de `Caixa::saldoCalculado()` (`caixa->lancamentos`). A ponte entre os dois eixos na mesma tela é a marca `tocaGaveta` na linha (⟺ a conta da baixa é do tipo `Caixa`), e ela **fecha por construção**: `abertura + Σ(linhas marcadas) = saldo esperado` (travado por teste).

6. **`Movimentos da gaveta` é removido da tela do Caixa.** `Recebimentos do dia por forma` sobrevive como a aba **`Por forma`** da timeline — mesma leitura, agregada, sem o detalhe expansível que agora é redundante com a lista.

**Migração:** nenhuma. Não há mudança de schema, de motor de baixa nem de saldo — só de leitura e apresentação.

## Consequências

### Positivas
- **A tela responde a pergunta que o lojista faz**: o que entrou, o que saiu, e o que sobrou hoje — em qualquer forma de pagamento.
- **Despesa paga passa a existir** no caixa do dia, com fornecedor, categoria e conta de origem.
- **Fim da duplicação**: o razão da conta-caixa tem um só lugar (`/contas/{conta}/extrato`), que já filtra por mês e exporta.
- **Dia sem caixa aberto deixa de ser beco sem saída** — o cenário mais comum de quem vende só no cartão.
- **Os dois eixos coexistem de forma auditável**: a marca de gaveta na linha reconcilia com o bloco de fechamento.

### Negativas
- **Duas fontes na mesma lista** exigem disciplina permanente: qualquer categoria nova de `Lancamento` que espelhe uma baixa tem de ficar fora da timeline, ou o dia dobra. Mitigado pelo teste de anti-duplicação e pelo comentário no topo do service.
- **A timeline é por dia**, sem paginação. Uma loja de alto volume terá listas longas; o corte por período continua em `/caixas/recebimentos` (só entradas). Unificar período fica para depois.
- Uma linha de despesa **não tem link de drill-down** — não existe tela de detalhe de despesa (a rota `despesas` é `except(['show'])`).

### Neutras
- O ADR-0011 segue valendo inteiro: continua não havendo saldo de banco, e a gaveta é o único saldo vivo.
- `Recebivel` segue dormente; nada aqui o ressuscita.
