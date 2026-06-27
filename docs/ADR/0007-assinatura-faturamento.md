# ADR-0007 — Assinatura, faturamento mensal e troca de plano pro-rata

## Status

Substituído por [ADR-0008](0008-transicao-plano-upgrade-downgrade.md) — junho/2026. A visão de
assinatura, o faturamento mensal e a fórmula pro-rata seguem válidos; a **troca de plano** (upgrade
imediato × downgrade agendado, não-sobrescrita de fatura paga, enum de status e marcação manual de
pagamento) passou a ser regida pelo ADR-0008.

Aceito — junho/2026.

## Contexto

A rede (tenant raiz) pertence a um `Plano` com preço mensal e limites (`max_empresas`,
`max_usuarios`, flags `tem_estoque`/`tem_financeiro`). Faltava: (1) uma visão de assinatura para o
Admin acompanhar plano, uso e histórico de cobrança; (2) um caminho real de **troca de plano** — a
tela existia, mas o botão só exibia um aviso de "fale com o suporte", sem efeito.

Restrições de modelagem já existentes:

- Há **uma fatura por mês por rede** (`unique(rede_id, referencia)` em `faturas`). Não dá para emitir
  uma segunda fatura no mesmo mês para cobrar um ajuste.
- `faturas.status` é string (`em_aberto`, `paga`, `vencida`, `cancelada`) — mantido como veio do WIP
  para não reescrever a tela; um enum `StatusFatura` fica como melhoria futura.
- Este é um produto de portfólio: **não há gateway de pagamento**. As faturas são geradas/marcadas
  internamente (histórico retroativo de demonstração via `AssinaturaController::garantirHistoricoFaturas`).

## Decisão

### Troca de plano é imediata e self-service para o Admin

- Autorização via `FaturaPolicy`: `viewAny` (qualquer usuário autenticado vê a assinatura da própria
  rede — o isolamento é do `RedeTrait`) e `transicionar` (somente `Admin`). A troca é um `POST` em
  `assinatura.transicionar`, validado por `TransicionarPlanoRequest` e executado por
  `TransicionarPlanoAction`.
- **Validação de limites (downgrade)**: o plano destino não pode deixar a rede acima dos seus limites.
  Se `uso_empresas > max_empresas` ou `uso_usuarios > max_usuarios` (limite `0` = ilimitado), a Action
  lança `NegocioException` e nada muda. Isso impede um downgrade que deixaria o tenant inconsistente.

### Ajuste pro-rata na fatura do mês vigente

Como só existe uma fatura por mês, o efeito financeiro da troca recai sobre a fatura do mês corrente,
cobrando os dias já decorridos no preço antigo e os dias restantes no preço novo:

```
valor = (preco_antigo * dias_decorridos + preco_novo * dias_restantes) / dias_no_mes
```

onde `dias_decorridos = dia_de_hoje - 1` e `dias_restantes = dias_no_mes - dias_decorridos`. A Action
atualiza a fatura `em_aberto` do mês (mesmo registro, sem violar o `unique`); se não houver, cria uma
com vencimento no fim do mês. Os meses seguintes já são faturados no plano novo (`rede.plano_id`
atualizado). Tudo numa transação (`DB::transaction`).

## Consequências

### Positivas

- Troca de plano vira um fluxo real, validado e auditável, em vez de um aviso decorativo.
- O downgrade é seguro: nunca deixa a rede acima dos limites do plano escolhido.
- O pro-rata respeita a regra de uma-fatura-por-mês sem gambiarra de duplicar faturas.
- Coberto por testes Feature (`tests/Feature/Tenant/AssinaturaTest.php`): visão, troca com pro-rata,
  ajuste de fatura existente, bloqueios de limite, mesmo-plano e 403 de não-admin.

### Negativas

- O pro-rata usa o dia do mês como proxy de "dias no plano antigo" — assume no máximo uma troca
  relevante por mês. Trocas múltiplas no mesmo mês recalculam sobre o preço antigo corrente, não sobre
  um rateio acumulado. Aceitável para o escopo (sem cobrança real).
- Sem gateway, "pagar" a fatura é uma marcação interna; integração (Stripe/Mercado Pago/Asaas) e
  webhooks ficam no roadmap.

### Neutras

- `faturas.status` segue como string; migrar para um enum `StatusFatura` é um refino isolado.
- A autorização de troca é por papel `Admin` (não por permissão granular) — coerente com decisões de
  cobrança serem do dono da rede; pode virar permissão dedicada se o RBAC crescer.
