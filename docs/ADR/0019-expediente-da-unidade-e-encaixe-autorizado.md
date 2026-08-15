# ADR-0019 — Expediente da unidade: fora do horário é encaixe autorizado, não acidente

## Status

Aceito — agosto/2026. Complementa o [ADR-0018](0018-agendamento-e-operacao-venda-e-financeiro.md),
que tratou a fronteira entre agenda e financeiro; aqui a fronteira é o relógio.

## Contexto

A agenda não tinha horário. A única coisa que parecia configuração era
`hourStart: 8 / hourEnd: 21` em `resources/js/calendar.js` — e isso é **janela de visualização** do
Toast UI Calendar, não regra: apenas define quais linhas a grade desenha. Nenhuma tabela, nenhuma
coluna, nenhuma validação no servidor.

O resultado prático:

1. **Qualquer horário era aceito.** `POST /agenda/criar-rapido` gravava 23h40 de domingo sem uma
   pergunta no caminho.
2. **`reagendar` não validava nada** — nem conflito. O drag-and-drop do calendário permitia arrastar
   um evento por cima de outro e empilhar dois clientes no mesmo atendente. A verificação de conflito
   existia, mas morava privada dentro de `CriarAgendamentoAction`, então só a criação a enxergava.
3. **A venda de serviço também não olhava a hora**, e criava agendamento pelo mesmo caminho.

Havia ainda um problema de forma: as três portas de escrita da agenda (criação rápida, edição pelo
formulário e reagendamento) validavam cada uma por conta própria — ou não validavam.

## Decisão

### Expediente é dado, e mora na unidade

Nova tabela `horarios_atendimento` (`rede_id`, `empresa_id`, `usuario_id` **nullable**, `dia_semana`,
`hora_inicio`, `hora_fim`, `ativo`). Uma linha por dia da semana. `usuario_id` nulo = expediente da
empresa inteira.

A coluna `usuario_id` já nasce porque horário **por atendente** é a evolução óbvia (cada profissional
com sua janela) e o resolvedor já a prefere quando existe. A v1 expõe só a UI da empresa — na edição
da unidade, que é onde o cadastro dela já vive. Toda unidade nova nasce com expediente
(`CriarEmpresaAction`, junto com contas e formas de pagamento), e uma migration dá o padrão às que
já existiam.

**Unidade sem nenhuma linha não restringe.** É rede de segurança deliberada: recusar tudo por falta
de configuração deixaria a agenda inutilizável — inclusive para quem precisasse entrar nela para
consertar. A regra que ninguém definiu não tranca a porta.

### Uma peça valida, três portas consomem

`VerificarDisponibilidadeAction` responde às duas perguntas de todo horário — **o atendente está
livre?** e **a unidade está aberta?** — e é usada por `criarRapido`, `update` e `reagendar` (além da
venda de serviço, única e em etapas). O `verificarConflito` saiu de dentro de `CriarAgendamentoAction`
para viver ali.

### As duas respostas não têm o mesmo peso

- **Conflito é "não".** Dois clientes no mesmo atendente no mesmo horário não é exceção, é erro. Não
  há como forçar.
- **Fora do expediente é "quer mesmo?"** Encaixar cliente às 19h acontece todo dia na vida real.
  Proibir seria trocar um problema (o sistema não sabe o horário) por outro pior (o sistema atrapalha
  o negócio).

O encaixe é, portanto, uma decisão consciente, autorizada e registrada:

- o servidor recusa com **422 + `codigo: fora_expediente`** (código estável — texto de mensagem não é
  contrato);
- a tela pergunta "encaixar mesmo assim?" e reenvia com `forcar_horario`;
- forçar exige a permissão nova **`agendamento.forcar_horario`**, separada de `agendamento.criar`:
  recepção agenda, mas quem decide abrir a loja fora do horário é quem responde pela unidade;
- o agendamento nasce com `fora_expediente = true` e aparece marcado como **Encaixe** no calendário.

Na tela de **venda** o loop de confirmação ainda não existe: quem tem a permissão passa direto (e o
agendamento fica marcado), quem não tem recebe a mesma recusa com a janela na mensagem. Fica anotado
como próximo passo.

### O calendário passa a desenhar o expediente

`hourStart`/`hourEnd` vêm da configuração da unidade (com uma hora de folga de cada lado, para o
encaixe aparecer na grade em vez de ficar escondido fora dela). A sidebar mostra a janela vigente.

## Consequências

**Positivas**

- Existe uma janela de atendimento de verdade, e sair dela é rastreável (`fora_expediente` + log de
  atividade) em vez de silencioso.
- O bug do drag-and-drop morreu: reagendar valida conflito como a criação sempre validou.
- Editar pelo formulário passou a recalcular o `fim` quando o início muda — antes sobrava o fim
  antigo, que podia acabar **antes** do novo início.
- De passagem: `criarRapido` deixou de estourar `NOT NULL` em `empresa_id` para Admin com várias
  unidades e nenhuma em contexto. A empresa passa a ser resolvida na Action — a mesma que decide qual
  expediente vale.

**Negativas / custos**

- Uma tabela e uma coluna novas, mais uma permissão para os perfis existentes considerarem.
- Quem opera fora do horário com frequência vai ver a pergunta com frequência. É o preço de tornar a
  exceção visível; se incomodar, o caminho é ajustar o expediente, não remover a checagem.
- A validação por faixa exige que o atendimento comece **e** termine dentro da janela: um serviço de
  1h às 17h30 numa unidade que fecha às 18h vira encaixe. É o comportamento correto, mas é uma
  mudança perceptível para quem emendava atendimentos no fim do dia.

**Neutras**

- Horário por atendente está modelado no banco e resolvido no código, mas sem UI. Bloqueios pontuais
  (férias, folga, almoço) continuam fora de escopo.
- Dias sem expediente não são sombreados na grade: o Toast UI v2 não expõe isso sem acoplar ao DOM
  interno dele, e a janela + o resumo na sidebar já comunicam o mesmo.
