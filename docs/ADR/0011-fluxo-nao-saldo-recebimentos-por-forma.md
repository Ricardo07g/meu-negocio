# ADR-0011 — Fluxo, não saldo: recebimentos por forma no caixa do dia

## Status

Aceito — julho/2026. **Supersede parcialmente o ADR-0010**: aposenta os **recebíveis** de cartão/pix-maquineta e o **saldo de banco** (o eixo "a cair / disponível / data prevista"). Mantém do ADR-0010 a `Conta`, o `Lancamento` e a **conta-caixa (gaveta)** como sessão diária — mas o `Lancamento` passa a existir **só** para a gaveta.

## Contexto

O ADR-0009/0010 modelou o dinheiro de cartão como **recebível** (D+N, líquido de taxa), que entrava no saldo da conta banco pela **data prevista**. Ao usar, o dono concluiu que isso ficou **complexo demais de operar e representar**, por três razões:

1. **Qualquer saldo que a gente calcule desatualiza.** O lojista **antecipa com a operadora, saca do banco, movimenta por fora** do sistema. Então tanto a "data prevista" do recebível quanto um "saldo do banco" viram ficção — pior do que não mostrar.
2. **Repasse é domínio do banco/operadora, não nosso.** Se o dinheiro já foi repassado ou não, quando caiu, com qual taxa efetiva após antecipação — nada disso é observável nem confiável pelo sistema.
3. **Dois lugares para ver o mesmo dinheiro.** O lojista tinha que cruzar o Caixa Diário com as Contas. Ele quer o simples: **o que entrou na empresa, e por qual forma**.

O que **nunca mente** é o registro de que *o cliente pagou* — a `BaixaPagamento`, com `data`, `forma_pagamento_nome` e valor. E o painel **"Recebimentos do dia por forma"** (ResumoDiaService), já construído no Caixa Diário, é exatamente a casa única para isso.

## Decisão

**Registrar fluxo (o que entrou/saiu, por forma), não saldo.** A `BaixaPagamento` é o registro do recebimento; o Caixa Diário é onde tudo aparece por forma.

1. **A `BaixaPagamento` é a verdade do recebimento por forma.** Toda baixa carrega `forma_pagamento_nome` + `data` (quando o cliente pagou). O painel do dia lê por ela — regime **"quando o cliente pagou"**, não a liquidação.

2. **Só a conta Caixa (gaveta) gera `Lancamento`.** É o único saldo "de verdade", reconciliado na contagem física de abertura/fechamento. **Todas as outras formas** (cartão, pix direto ou maquineta, boleto, crediário, banco) registram **só a Baixa** — sem `Lancamento`, sem recebível. O eixo de decisão do motor (`aplicarBaixaParcela`) deixa de olhar `gera_recebivel` e passa a ser: **conta destino é do tipo Caixa?** Se sim, exige caixa aberto e grava o `Lancamento` na gaveta; se não, só a Baixa.

3. **Recebível aposentado.** `Recebivel` **não é mais gerado**. Somem "a cair / disponível / data prevista / valor líquido de taxa / repasse" da UI (dashboard "Recebíveis a cair", extrato "A cair", saldo de banco). Na Fatia 1 o model/tabela ficam **dormentes**; a remoção física fica para a Fatia 2.

4. **Antecipação é informativa.** Os campos da forma (`antecipacao_automatica`, `taxa_antecipacao_mensal`) permanecem como marcador ("essa maquineta antecipa? sim/não") — **não ligam** a datas nem a valores. O acerto com a operadora é papel do lojista.

5. **Conta banco/carteira vira rótulo de origem.** `forma.conta_destino_id` sobrevive como etiqueta (Cielo, Rede, banco X) para o lojista saber a origem do recebimento — sem saldo vivo. `Conta::saldo() = saldo_inicial + Σcréditos − Σdébitos` (só a gaveta acumula lançamentos na prática).

6. **Estorno marca a baixa.** Ao cancelar a venda, cada `BaixaPagamento` ganha `estornado_em` — o **marcador único** que o painel do dia neta (recebido − estornado, pelo bruto da baixa, pela data do estorno). Só a baixa da **gaveta** tem `Lancamento` a reverter (contra-lançamento de débito, com o guard de caixa fechado preservado); cartão/pix/banco não têm lançamento — nada a reverter.

**Migração:** ambiente local, sem conversão de dados (reseed). Uma migration adiciona `estornado_em` (nullable) a `baixas_pagamento`.

## Consequências

### Positivas
- **Simples de operar e representar.** Um lugar (o caixa do dia) mostra tudo por forma; nada de saldos que desatualizam.
- **Menos código.** Sai a geração de recebíveis, o cálculo de datas/taxas/líquido no motor, os cards de "a cair" e o saldo de banco. O painel do dia até **simplifica** (estorno por `estornado_em`, sem a dupla-fonte Lancamento∪Recebivel).
- **Escopo honesto.** O sistema para de prometer o que não observa (repasse/liquidação).

### Negativas / limites
- **Não há visão de "quanto de cartão está pra cair".** Por decisão: isso é do banco/operadora.
- **Contas banco/carteira não têm saldo controlado** — são rótulos. Quem quiser saldo bancário real precisa de conciliação (fora de escopo).
- **Débito técnico transitório:** `Recebivel` (model/tabela/enum) e os campos de taxa/antecipação de recebível ficam dormentes até a Fatia 2.

### Neutras
- A **gaveta** (conta Caixa, sessão diária, sangria/reforço, saldo físico) segue igual ao ADR-0010.
- `darBaixaParcelaPagamento` mantém o parâmetro `parcelasCartao` por compatibilidade (ignorado; removido na Fatia 2).

## Faseamento
- **Fatia 1 (esta):** para de gerar recebíveis; `estornado_em`; motor e estorno por gaveta; remove UI de saldo/a-cair; antecipação informativa; docs e testes.
- **Fatia 2 (pendente):** remoção física de `Recebivel` (model + tabela + `StatusRecebivel`) e das taxas/antecipação que só serviam ao recebível.
