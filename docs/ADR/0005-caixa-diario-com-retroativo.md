# ADR-0005 — Caixa diário com abertura retroativa permitida

## Status

Aceito — abril/2026.

## Contexto

O módulo Caixa modela o caixa do dia: 1 caixa por empresa por dia, com abertura, movimentos (vendas, sangria, reforço, baixas a prazo), fechamento e — ocasionalmente — reabertura para correção. O ponto a decidir é se o sistema permite **operar em datas passadas** ou trava o usuário no "hoje".

O perfil real do usuário (pequeno negócio, salão, clínica) costuma:

- Esquecer de abrir o caixa pela manhã e perceber só ao final do dia (ou no dia seguinte).
- Lançar uma venda que aconteceu "ontem" mas só foi inserida no sistema agora.
- Precisar corrigir um movimento errado de uma data passada (ex.: forma de pagamento trocada).
- Reabrir o caixa de ontem para baixar uma parcela paga em atraso.

Duas opções foram avaliadas:

1. **Caixa restrito a "hoje"**: força disciplina, simplifica auditoria, reflete a realidade contábil tradicional. Mas frustra usuários reais e gera operações fora do sistema (planilhas paralelas) — exatamente o problema que o produto vem resolver.
2. **Caixa retroativo permitido**: data do caixa é parametrizável, navegação prev/next por dia, abrir/fechar/reabrir em qualquer data. Aceita a realidade do usuário, mas obriga uma estratégia robusta de identificação de "qual caixa estamos operando".

## Decisão

Adotamos **caixa retroativo permitido**, com as seguintes regras:

- A view de Caixa Diário tem navegação prev/next por dia (`?data=YYYY-MM-DD`). Default = hoje.
- Para cada dia, há **no máximo um Caixa por empresa** (constraint `unique (empresa_id, data_referencia)`).
- O usuário pode **abrir um caixa em data passada** se ainda não houver caixa para aquela data.
- O usuário pode **reabrir** um caixa fechado e operar de novo (movimentos, baixas), depois fechar novamente.
- Movimentos novos (sangria, reforço) e baixas de parcelas a prazo são lançados **no caixa do dia em que se está operando** — não migram para "hoje".
- Vendas à vista exigem o **caixa daquele dia aberto** antes de processar (validação no controller, antes da transaction).
- Toda alteração de Caixa é auditada via `RegistraAtividade` (Spatie ActivityLog).
- Cancelamento/estorno de venda paga gera **movimento de saída no caixa do dia atual** (não no caixa original) — a auditoria preserva o caixa antigo intacto.

## Consequências

### Positivas
- **Flexibilidade operacional real**: o usuário corrige erros, registra atrasos e mantém os dados completos sem precisar de planilhas paralelas.
- **Auditável**: cada operação fica logada em `activity_log` com `causer_id`, timestamp e diff.
- **Simples no código**: o Caixa carrega `data_referencia` e `Service` opera sobre ele sem inferir "hoje" implícito.
- **Estorno coerente**: gera movimento no caixa atual em vez de mexer em caixa fechado historicamente.

### Negativas
- **Risco de adulteração**: usuário mal-intencionado pode reabrir caixa de mês passado e mudar valores. Mitigado por activity log + (futuramente) restrição por papel (apenas Admin reabre datas anteriores a X dias).
- **Auditoria contábil exige cuidado**: relatório de caixa por período precisa cruzar com movimentos pós-fechamento e reaberturas. Hoje o relatório financeiro está fora de escopo do portfólio (ver [README#roadmap](../../README.md#roadmap)).
- **Mais fricção no checklist mental**: o dev que mexer em Caixa precisa pensar "qual data?" em vez de assumir "hoje".

### Neutras
- O comportamento poderia ser endurecido no futuro com flag por plano ("plano básico = só hoje", "plano pro = retroativo de até 30 dias"). Hoje todos os planos têm acesso retroativo livre.
