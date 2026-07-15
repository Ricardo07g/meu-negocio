# ADR-0009 — Formas de pagamento configuráveis e recebíveis de cartão

## Status

Aceito — julho/2026.

## Contexto

Até aqui, `forma_pagamento` era um enum PHP fixo (`pix`, `dinheiro`, `cartao`, `boleto`) gravado como string na parcela e na baixa. Toda baixa passava por `CaixaService::aplicarBaixaParcela()`, que **sempre** exigia caixa aberto e **sempre** criava um `MovimentoCaixa` com o valor cheio — ou seja, dinheiro de cartão entrava na gaveta do caixa igual a dinheiro vivo.

Isso confunde dois "a receber" distintos:

- **A receber do cliente** — só existe em venda a prazo (fiado). Quando o cliente paga no cartão, ele **não deve nada** à loja: a obrigação dele é quitada na hora do swipe.
- **A receber do banco/adquirente** — o dinheiro do cartão **não está na gaveta**. O adquirente paga depois (débito ≈ D+1, crédito ≈ D+30), **descontando a taxa (MDR)**.

Além disso, cada lojista tem sua própria maquininha, com taxas e prazos próprios — um enum fixo não modela isso. Alternativas consideradas: (a) manter o enum e só marcar cartão como "pago"; (b) modelo completo com recebíveis, taxa por faixa de parcelas e conciliação de extrato do adquirente. A conciliação por arquivo (parser por adquirente) foi descartada por complexidade e por depender de arquivos reais.

## Decisão

**Catálogo de formas configurável por rede + recebíveis de cartão derivados por data.**

1. **`formas_pagamento`** (catálogo rede-level, soft delete): CRUD livre e nomeado ("Crédito Cielo"), cada forma com um `tipo` base (enum `TipoFormaPagamento`: dinheiro/pix/cartao_debito/cartao_credito/boleto), `gera_recebivel`, `dias_liquidacao` (D+N), `taxa_percentual` e faixas de taxa por nº de parcelas (`formas_pagamento_taxas`). O enum antigo `FormaPagamento` foi removido; parcelas/baixas passam a referenciar `forma_pagamento_id` (FK) + um snapshot `forma_pagamento_nome` (para renome/soft-delete não quebrar histórico). `movimentos_caixa` guarda só o snapshot (sem FK — cartão nunca gera movimento).

2. **Cartão quita o cliente na hora, mas não entra no caixa.** Numa baixa com forma `gera_recebivel = true`: a parcela é quitada e a `BaixaPagamento` é criada (dashboard soma baixas, então o faturamento não muda), mas **não** se exige caixa aberto e **não** se cria `MovimentoCaixa`. Em vez disso geram-se **N recebíveis** (um por parcela do cartão), com `valor_liquido = bruto × (1 − taxa/100)` e `data_prevista = data_venda + dias_liquidacao + 30×(i−1)`. Débito/crédito-à-vista → 1 recebível; crédito Nx → N recebíveis mensais.

3. **O nº de parcelas do cartão é independente de `CondicaoPagamento`.** Um novo escalar `parcelas_cartao` é passado da venda até a baixa e define a faixa de taxa + a agenda dos recebíveis. Cartão é sempre à-vista no ledger do cliente (1 parcela paga); a `CriarPagamentoComParcelasAction` e o `CalculadoraParcelas` não mudam.

4. **Status do recebível é derivado pela data (sem job):** `cancelado_em` ⇒ Cancelado; senão `data_prevista <= hoje` ⇒ Recebido, senão Previsto (espelha `ParcelaPagamento::statusEfetivo()`).

5. **Estorno é por-baixa:** ao cancelar uma venda, cada baixa de cartão (sem `caixa_id`) cancela seus recebíveis (`cancelado_em`), sem gerar saída no caixa; baixas em dinheiro/pix mantêm a saída no caixa de origem.

6. **Despesa (contas a pagar) nunca gera recebível** — sempre saída de caixa. O motor compartilhado recebe um `bool $geraRecebivel` explícito (a despesa força `false`), em vez de confiar só em `forma->gera_recebivel`.

**Faturamento permanece por competência / valor bruto na data da venda** (o dashboard soma `BaixaPagamento`), não por caixa em D+N. É uma escolha de produto: "quanto vendi" ≠ "quanto já caiu na conta".

Fora de escopo nesta fase: tela rica de recebíveis + antecipação (fase futura), conciliação de extrato do adquirente (descartada), captura de bandeira (taxa é por forma + faixa de parcelas).

## Consequências

### Positivas
- **Conceito financeiro correto**: cartão parcelado some de Contas a Receber do cliente e o caixa (gaveta) só reflete dinheiro/pix — deixa de ser "inflado" por dinheiro que ainda não chegou.
- **Configurável por lojista**: cada rede define suas formas, taxas e prazos.
- **Zero infra nova para status**: recebido/previsto é calculado pela data; sem cron nem worker.
- **Histórico robusto**: o snapshot `forma_pagamento_nome` preserva a exibição mesmo após renome/soft-delete da forma.
- **Dashboard intacto**: como o cartão continua gerando `BaixaPagamento`, o faturamento não subconta.

### Negativas
- **Raio de impacto grande**: remover o enum tocou ~18 arquivos (controllers/services/actions/DTOs/models/factories/tests) e 5 tabelas — mitigado por PHPStan + suíte.
- **Buraco de tenancy no `exists`**: a validação de `forma_pagamento_id` precisa filtrar `rede_id` na mão (o `Rule::exists` cru ignora o global scope). Coberto por teste.
- **Faturamento ≠ caixa**: "vendi R$ 1.000" pode não bater com "R$ 1.000 na conta" (D+N e taxa). Aceitável e explícito, mas exige comunicação na UI de relatórios (fase futura).
- **Agenda D+N-mensal é uma aproximação**: adquirentes reais variam; a conciliação exata fica para uma fase futura.

### Neutras
- Boleto e "pix parcelado" seguem tratados como imediatos (caixa) por ora; podem virar recebíveis no futuro sem mudar o modelo.
- Antecipação (simular/antecipar recebíveis) e uma tela dedicada de recebíveis são evolução natural desta base.
