# ADR-0002 — Modelo financeiro Título + Parcela + Baixa

## Status

Aceito — abril/2026.

## Contexto

O domínio financeiro do Meu Negócio precisa cobrir cenários reais de pequenos negócios:

- Venda **à vista** com pagamento integral no caixa do dia.
- Venda **a prazo** parcelada em N vezes, com parcelas pendentes virando "Contas a Receber".
- **Baixa parcial** de uma parcela (cliente paga só parte e fica devendo o resto).
- **Renegociação** (mudança de data, valor ou número de parcelas em parcelas pendentes).
- **Múltiplas baixas na mesma parcela** (entrou parte em pix, parte em dinheiro, em datas diferentes).
- **Estorno automático** ao cancelar uma venda já paga.
- O mesmo modelo precisa servir tanto para **a receber (Pagamento)** quanto para **a pagar (Despesa)**.

A modelagem ingênua "uma linha por transação" — uma tabela `pagamentos` com `valor`, `forma_pagamento`, `status` direto — resolve venda à vista mas quebra em parcelamento, baixa parcial e múltiplas formas de pagamento na mesma parcela.

## Decisão

Adotamos a tríade **Título + Parcela + Baixa**, espelhada para Pagamento (a receber) e Despesa (a pagar):

- **Título** (`Pagamento` / `Despesa`) — registra o "compromisso financeiro": valor bruto, valor líquido, condição (`a_vista`, `a_prazo`, `boleto`, `pix_parcelado`), forma de recebimento esperada, originador (venda, despesa avulsa). **Não** carrega `forma_pagamento` real — quem pagou só se sabe na baixa.
- **Parcela** (`ParcelaPagamento` / `ParcelaDespesa`) — quebra do título em N pedaços com `numero`, `data_vencimento`, `valor`, `valor_pago`, `status` (`Pendente`, `Pago`, `ParcialmentePago`, `Cancelado`). Geração via `App\Support\Parcelamento\CalculadoraParcelas`.
- **Baixa** (`BaixaPagamento` / `BaixaDespesa`) — registra cada movimentação real de pagamento de uma parcela: caixa em que entrou, valor, multa, juros, desconto, forma de pagamento real. **Uma parcela tem N baixas**.

A consequência prática: `forma_pagamento` mora na **parcela** (e por extensão na baixa), não no título. O que indica "fiado" agora é `condicao_pagamento = a_prazo`.

## Consequências

### Positivas
- **Cobre todos os cenários** acima sem cases especiais.
- **Auditável**: cada baixa é uma linha imutável vinculada a um caixa do dia, fácil de reconciliar.
- **Estorno coerente**: ao cancelar uma venda paga, varre as parcelas, soma os valores pagos e gera um movimento de saída no caixa atual; parcelas pendentes viram `Cancelado`.
- **Reusabilidade**: Pagamento e Despesa compartilham a mesma topologia (com classes próprias por motivo de domínio diferente), o que faz o time mental do dev "uma vez e funciona dos dois lados".

### Negativas
- **Mais tabelas** (3 vs 1) e mais joins. Listas precisam carregar `with(['parcelas.baixas'])` para evitar N+1.
- **Curva de aprendizado**: novo dev no projeto leva alguns minutos para entender por que o status fica na parcela e a forma de pagamento na baixa.
- **Setup mínimo**: até para uma venda de R$ 50 à vista o sistema cria 1 título + 1 parcela + 1 baixa. Para a escala atual é trivial; em escala industrial seria um custo a observar.

### Neutras
- O padrão é o mesmo adotado por sistemas financeiros profissionais (ERPs como Omie, Tiny). Reaproveitar a nomenclatura facilita o onboarding de quem vem de mercado.
