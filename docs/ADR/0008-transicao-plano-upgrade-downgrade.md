# ADR-0008 — Troca de plano: upgrade imediato, downgrade agendado e fatura por status

## Status

Aceito — junho/2026. Substitui o [ADR-0007](0007-assinatura-faturamento.md) na parte de troca de
plano e ajuste de fatura.

## Contexto

O ADR-0007 definiu a assinatura (visão "Minha Assinatura", `Fatura`s mensais internas, troca
self-service com pro-rata). Na prática a `TransicionarPlanoAction` tinha três lacunas:

1. **Fatura do mês sobrescrita sem olhar o status.** O ajuste pro-rata fazia `UPDATE` em `valor` e
   `plano_id` da fatura do mês corrente independentemente de ela estar `em_aberto`, `paga` ou
   `vencida`. Uma fatura **já paga** ficava inconsistente (o cliente "pagou" R$ 100 e a fatura passava
   a dizer R$ 153, sem cobrar nem creditar a diferença).
2. **Sem distinção entre upgrade e downgrade.** Qualquer troca era imediata e recaía na fatura do mês,
   o que para um downgrade significa "pagar menos já neste mês" — o oposto do padrão de mercado.
3. **Sem prévia.** O modal confirmava a troca sem mostrar o efeito financeiro; e `faturas.status` era
   string solta, sem um caminho de "marcar como paga".

Restrições herdadas (mantidas): **uma fatura por mês por rede** (`unique(rede_id, referencia)`) e
**sem gateway de pagamento** — faturas são marcações internas (histórico de demonstração gerado por
`AssinaturaController::garantirHistoricoFaturas`).

## Decisão

### Upgrade é imediato; downgrade vale no próximo ciclo

A `TransicionarPlanoAction` distingue os casos pelo `preco_mensal` do plano destino vs. o atual:

- **Upgrade** (preço destino ≥ atual — preço igual com plano diferente conta como upgrade): efeito
  imediato. Troca `rede.plano_id` agora (libera recursos) e ajusta a fatura do mês pro-rata **somente
  se ela estiver `em_aberto`**. Fatura `paga`/`vencida` **não é tocada**: o upgrade vale a partir da
  próxima fatura (concessão consciente do "resto do mês no plano maior", benigna sem cobrança real, e
  inevitável dado o `unique` que impede uma segunda fatura no mês).
- **Downgrade** (preço destino < atual): **agendado** para a virada do mês via nova coluna
  `redes.plano_agendado_id` (FK nullable, `nullOnDelete`). Não mexe em `rede.plano_id` nem na fatura —
  a rede mantém o plano e os recursos até o fim do ciclo, sem reembolso.
- Escolher de novo o **plano atual** quando há um downgrade agendado **cancela** o agendamento (em vez
  de lançar "já está nesse plano").

A fórmula pro-rata (inalterada) vive agora em `App\Modules\Tenant\Support\CalculadoraProRata`, fonte
única usada tanto pela Action (efeito real) quanto pelo controller (prévia):

```
valor = (preco_antigo * dias_decorridos + preco_novo * dias_restantes) / dias_no_mes
```

### Aplicação lazy do agendamento na virada

Sem scheduler, a troca agendada é aplicada de forma **lazy** ao abrir a tela:
`AssinaturaController::aplicarPlanoAgendadoSeViravelMes` roda antes de ler o plano vigente e, se a
última fatura é de um mês anterior ao atual, promove `plano_agendado_id → plano_id` e a fatura do novo
mês já nasce no plano novo. **Limite revalidado na virada**: se nesse meio-tempo a rede passou a
exceder os limites do destino, o agendamento é cancelado (log warning) e o plano atual é mantido.

### Validação de limites no agendamento

O downgrade é validado **no momento de agendar** (feedback imediato ao admin) e **revalidado na
virada** — defesa em profundidade contra mudanças de uso entre os dois momentos.

### Fatura com enum de status e marcação manual de pagamento

`faturas.status` passa a ser o enum `App\Enums\StatusFatura` (cast PHP sobre a coluna `string` — sem
migration de dados, coerente com os demais enums do projeto). Adicionada a ação **marcar como paga**
(`assinatura.fatura.pagar`, `FaturaPolicy::pagar` = Admin) e, no modal, a **prévia** do efeito da
troca antes de confirmar.

## Consequências

### Positivas

- Fatura paga nunca é corrompida; o efeito financeiro da troca é previsível e explicado ao usuário
  antes de confirmar.
- Upgrade/downgrade seguem o padrão de mercado (sobe já, desce no próximo ciclo) sem reembolso.
- Cálculo pro-rata centralizado (`CalculadoraProRata`) — prévia e efeito real não divergem.
- Coberto por testes Feature (`tests/Feature/Tenant/AssinaturaTest.php`): upgrade com/sem ajuste,
  não-sobrescrita de fatura paga/vencida, downgrade agendado, aplicação e cancelamento na virada,
  marcar como paga e autorização.

### Negativas

- A aplicação do agendamento é **lazy** (disparada ao abrir a tela). Sem scheduler real, se ninguém
  abrir a assinatura a virada não acontece; e meses pulados entre o agendamento e a próxima visita são
  gerados já no plano novo. Aceitável para um produto de portfólio sem cobrança real; um job agendado
  fica no roadmap.
- O upgrade com fatura já paga concede o restante do mês no plano maior sem cobrança adicional
  (limitado pelo `unique` de uma fatura por mês).

### Neutras

- `faturas.status` continua `string` no banco (enum só na camada PHP).
- Suspensão automática de rede por inadimplência, vencimento automático (`em_aberto → vencida`) e
  integração de gateway/webhooks permanecem no roadmap (não escopo deste ADR).
