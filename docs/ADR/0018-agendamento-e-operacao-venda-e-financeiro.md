# ADR-0018 — Agendamento é operação, venda é financeiro: a cobrança acontece na finalização

## Status

Aceito — agosto/2026.

## Contexto

O sistema tinha **duas portas** que criavam a mesma coisa com resultados financeiros diferentes:

| porta | cria | virava receita? |
|---|---|---|
| `POST /vendas` (serviço único) | Agendamento **+ Pagamento** (título, parcelas, baixa) | sim |
| `POST /agenda/criar-rapido` | Agendamento **sem** título | não |

Isso, sozinho, não seria um problema — reservar um horário não é vender, e num salão ou clínica a
maior parte dos agendamentos nasce mesmo sem cobrança. O problema é que o resto do sistema não
sabia disso:

1. **A listagem de Vendas contava agendamento solto como venda.** `VendaService::listar` puxava
   *todo* `Agendamento` com `venda_etapas_id IS NULL` e o mapeava com `valor = servico.valor`. Um
   horário reservado no calendário aparecia na tela de Vendas exibindo um valor que ninguém havia
   recebido — sem título, sem parcela, sem baixa. Receita fantasma.
2. **Não existia caminho de agendamento → cobrança.** `FinalizarAgendamentoAction` só trocava o
   status. O atendimento acontecia, era marcado como finalizado, e nunca virava dinheiro. Quem
   quisesse cobrar tinha de ir à tela de Vendas e criar uma venda nova — que criava um *segundo*
   agendamento.
3. **"Não cobrei" era indistinguível de "esqueci de cobrar".** Os dois viravam o mesmo agendamento
   finalizado e mudo.
4. **Cancelar pela agenda não devolvia o dinheiro.** `CancelarAgendamentoAction` marcava o título
   como `Estornado` na mão: o rótulo mudava, mas não havia contra-lançamento (o dinheiro continuava
   na gaveta) e as parcelas a receber sobreviviam ao cancelamento.

## Decisão

**Agendamento é o fato operacional; venda é o fato financeiro.** São entidades distintas ligadas por
`pagamentos.agendamento_id` — FK que já existia e passa a ser a única fonte de verdade sobre "este
atendimento virou receita?".

### 1. Só é venda o que tem título

`VendaService::listar` filtra os agendamentos por `whereHas('pagamento')`. Agendamento sem título é
agenda, não venda, e não aparece como faturamento em lugar nenhum.

### 2. Finalizar exige um desfecho declarado

`FinalizarAgendamentoAction` passa a recusar (`NegocioException`) o encerramento de um atendimento
que não tem título **e** não tem motivo. As duas saídas possíveis:

- **Finalizar e cobrar** → cria o título e finaliza, na mesma transação
  (`VendaService::cobrarAtendimento`);
- **Finalizar sem cobrar** → finaliza gravando `agendamentos.motivo_sem_cobranca`
  (`MotivoSemCobranca`: cortesia, retorno, garantia, uso interno).

Agendamento que já tem título (venda pré-paga) finaliza direto: título e motivo são mutuamente
exclusivos.

### 3. A tela de cobrança é a tela de venda, em modo cobrança

`GET /vendas/nova?agendamento={id}` renderiza a mesma tela: o bloco de cliente/serviço/horário sai
de cena (continua no DOM, preenchido pelo JS a partir do agendamento) e o bloco de recebimento
segue idêntico — split de formas, crediário, preview do carnê. **Não existe uma segunda UI de
recebimento.** O valor cobrado vem sempre do serviço agendado, nunca do request.

O passo financeiro foi extraído de `criarUnico` para `criarTituloDoAgendamento`, de modo que
pré-pago e cobrança-no-fim compartilhem exatamente o mesmo código.

### 4. Cancelar e estornar são coisas diferentes

- **Atendimento ainda em aberto** → cancelar é cancelar o compromisso: some da agenda e o dinheiro
  volta. `CancelarAgendamentoAction` agora delega a `CaixaService::estornarPagamento`, o mesmo
  caminho do cancelamento de venda (contra-lançamento por baixa, `estornado_em`, recusa em caixa
  fechado — ADR-0011).
- **Atendimento já finalizado** → o serviço foi prestado; desfazer isso seria mentir sobre o
  passado. `VendaService::cancelarUnico` estorna apenas a cobrança e o agendamento continua
  `Finalizado`, exibindo situação "Estornado". Na tela de Vendas o botão se chama **Estornar
  cobrança**, não "Cancelar venda".

Como o estorno **não é idempotente** (cada passagem gera contra-lançamento), há guarda contra título
já desfeito nos dois caminhos, e `cancelarUnico` deixou de estornar por conta própria no caso em
aberto — quem estorna ali é a Action.

### 5. Situação financeira é derivada, nunca persistida

`Agendamento::situacaoFinanceira()` devolve `SituacaoFinanceiraAgendamento` (A cobrar / Sem cobrança
/ A receber / Pago / Estornado) a partir do título. Uma coluna criaria uma segunda verdade que
envelhece a cada baixa e a cada estorno. A situação aparece no popup do calendário e no detalhe do
agendamento — é o que a recepção precisa ver antes de liberar o cliente.

## Consequências

**Positivas**

- A tela de Vendas passa a mostrar apenas dinheiro real.
- Existe um caminho único e explícito de atendimento → receita, reusando todo o motor financeiro.
- Cortesia, retorno e garantia viram dado consultável em vez de buraco no faturamento.
- O estorno pela agenda deixa de ser um rótulo e passa a mover o caixa de verdade.
- Fechou-se, de passagem, um vazamento de tenancy: `exists:clientes,id` monta query própria e ignora
  o global scope de rede, então dava para amarrar um agendamento a cliente/serviço/atendente de
  outra rede trocando o id no POST. As regras agora vivem em `App\Support\Agenda\RegrasDeVinculo`,
  escopadas por `rede_id` (e por empresa, no caso do atendente).

**Negativas / custos**

- Finalizar ficou mais caro em cliques: quem só quer marcar "atendido" precisa dizer se cobrou ou
  por que não. É deliberado — é justamente o dado que faltava.
- `agendamentos` ganhou uma coluna (`motivo_sem_cobranca`).
- A tela de venda passa a ter dois modos. O modo cobrança mantém o bloco de dados no DOM (oculto)
  para não bifurcar o `venda-create.js`, que é grande; quem mexer nele precisa saber disso.

**Neutras**

- Agendamentos históricos sem título continuam sem título: nada é retroativo. Eles simplesmente
  deixam de aparecer na listagem de Vendas, que é onde nunca deveriam ter estado.
- O horário do agendamento continua sem validação de expediente — isso é o próximo passo, e não
  depende desta decisão.
