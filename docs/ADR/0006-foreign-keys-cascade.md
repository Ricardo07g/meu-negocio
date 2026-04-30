# ADR-0006 — Comportamento de foreign keys (cascade / null / restrict)

## Status

Aceito — abril/2026.

## Contexto

O projeto evoluiu em duas eras de migrations:

- **Era 1 (março/2026)**: tabelas `empresas`, `usuarios`, `clientes`, `servicos`, `agendamentos`, `produtos`, `movimentos_estoque`, `vendas_pacote`. Foram criadas com `->constrained('contas')` **sem declarar** `cascadeOnDelete()`, `nullOnDelete()` ou `restrictOnDelete()`. Resultado: as FKs ficam no default do MySQL (`RESTRICT`).
- **Era 2 (a partir de abril/2026)**: `pagamentos`, `parcelas_pagamento`, `despesas`, `parcelas_despesa`, `baixas_pagamento`, `baixas_despesa`, `movimentos_caixa`, `caixas`, `categorias_produto`, `categorias_despesa`, `empresa_usuario`. Já nascem com comportamento explícito (`cascadeOnDelete`, `nullOnDelete`).

Essa diferença gera inconsistência: por exemplo, `pagamentos.empresa_id` cascateia ao apagar a empresa, mas `agendamentos.empresa_id` falha com erro de constraint. Em produção isso seria bug; em projeto de portfólio **a intenção é documentar o padrão correto e marcar os pontos como débito técnico**, sem alterar migrations já rodadas (risco operacional sem ROI).

## Decisão

### Padrão para NOVAS migrations

| FK típica | Ação esperada | Justificativa |
|-----------|---------------|---------------|
| `rede_id` → `redes` | `cascadeOnDelete` | Se a rede some, todos os dados dela vão junto. É o limite natural do tenant. |
| `empresa_id` → `empresas` | `cascadeOnDelete` | Mesma lógica do tenant interno. Empresa apagada = dados transacionais dela apagados. |
| `usuario_id` → `usuarios` (em registros transacionais) | `nullOnDelete` ou manter `restrict` | Histórico transacional não deve sumir junto com o usuário. Preferir nullable + null on delete; manter restrict se a coluna **não puder** ser nula (revisar decisão por caso). |
| Título → Parcela (`pagamento_id`, `despesa_id`) | `cascadeOnDelete` | Parcela só existe enquanto o título existe. |
| Parcela → Baixa | `cascadeOnDelete` | Baixa não faz sentido sem parcela. |
| Baixa → Caixa (`caixa_id`) | `nullOnDelete` | Caixa apagado é raríssimo, mas baixa preserva a informação financeira mesmo perdendo o vínculo. |
| `cliente_id` em transação | `nullOnDelete` | Preservar histórico de venda/agendamento mesmo se o cliente for removido. |
| `produto_id`, `servico_id` em transação | `restrictOnDelete` (default) | Bloquear remoção de catálogo enquanto houver vínculo histórico. Soft-delete do catálogo resolve o caso do dia-a-dia. |
| `categoria_*` (produto/despesa) | `nullOnDelete` | Categoria é classificação solta; o registro principal sobrevive sem ela. |

### Auditoria das FKs existentes

Tabela atual (D = inferido do default MySQL = `RESTRICT`):

| Tabela | Coluna | Referência | Comportamento atual | Status |
|--------|--------|------------|---------------------|--------|
| `redes` (renomeada de `contas`) | `plano_id` | `planos` | `RESTRICT` (D) | OK — bloquear apagar plano com redes |
| `empresas` | `rede_id` (renomeado de `conta_id`) | `redes` | `cascadeOnDelete` | OK |
| `usuarios` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `usuarios` | `empresa_id` | `empresas` | `RESTRICT` (D) — nullable | **Débito** — esperado `nullOnDelete` |
| `empresa_usuario` | `rede_id` | `redes` | `cascadeOnDelete` | OK |
| `empresa_usuario` | `empresa_id` | `empresas` | `cascadeOnDelete` | OK |
| `empresa_usuario` | `usuario_id` | `usuarios` | `cascadeOnDelete` | OK |
| `clientes` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `servicos` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `produtos` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `produtos` | `categoria_produto_id` | `categorias_produto` | `nullOnDelete` | OK |
| `categorias_produto` | `rede_id` | `redes` | `cascadeOnDelete` | OK |
| `categorias_despesa` | `rede_id` | `redes` | `cascadeOnDelete` | OK |
| `agendamentos` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `agendamentos` | `empresa_id` | `empresas` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `agendamentos` | `cliente_id` | `clientes` | `RESTRICT` (D) | OK (preserva agenda histórica; alternativa: nullOnDelete) |
| `agendamentos` | `servico_id` | `servicos` | `RESTRICT` (D) | OK (bloqueia apagar serviço com agenda) |
| `agendamentos` | `atendente_id` | `usuarios` | `RESTRICT` (D) | **Débito** — preferível `nullOnDelete` |
| `agendamentos` | `venda_pacote_id` | `vendas_pacote` | `nullOnDelete` | OK |
| `vendas_pacote` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `vendas_pacote` | `empresa_id` | `empresas` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `vendas_pacote` | `cliente_id` | `clientes` | `RESTRICT` (D) | OK (preserva histórico) |
| `vendas_pacote` | `servico_id` | `servicos` | `RESTRICT` (D) | OK |
| `vendas_pacote` | `atendente_id` | `usuarios` | `RESTRICT` (D) | **Débito** — preferível `nullOnDelete` |
| `vendas_produto` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `vendas_produto` | `empresa_id` | `empresas` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `vendas_produto` | `cliente_id` | `clientes` | `RESTRICT` (D) — nullable | **Débito** — esperado `nullOnDelete` |
| `vendas_produto` | `usuario_id` | `usuarios` | `RESTRICT` (D) | **Débito conhecido** (apontado em FECH-018) — preferível `nullOnDelete` |
| `venda_produto_itens` | `venda_produto_id` | `vendas_produto` | `cascadeOnDelete` | OK |
| `venda_produto_itens` | `produto_id` | `produtos` | `RESTRICT` (D) | OK (preserva itens históricos) |
| `movimentos_estoque` | `rede_id` | `redes` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `movimentos_estoque` | `empresa_id` | `empresas` | `RESTRICT` (D) | **Débito** — esperado `cascadeOnDelete` |
| `movimentos_estoque` | `produto_id` | `produtos` | `RESTRICT` (D) | OK (mantém histórico) |
| `pagamentos` | `rede_id` | `redes` | `cascadeOnDelete` | OK |
| `pagamentos` | `empresa_id` | `empresas` | `cascadeOnDelete` | OK |
| `pagamentos` | `cliente_id` | `clientes` | `nullOnDelete` | OK |
| `pagamentos` | `agendamento_id` | `agendamentos` | `nullOnDelete` | OK |
| `pagamentos` | `venda_pacote_id` | `vendas_pacote` | `nullOnDelete` | OK |
| `pagamentos` | `venda_produto_id` | `vendas_produto` | `nullOnDelete` | OK |
| `parcelas_pagamento` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `parcelas_pagamento` | `pagamento_id` | `pagamentos` | `cascadeOnDelete` | OK |
| `despesas` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `despesas` | `categoria_despesa_id` | `categorias_despesa` | `nullOnDelete` | OK |
| `parcelas_despesa` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `parcelas_despesa` | `despesa_id` | `despesas` | `cascadeOnDelete` | OK |
| `caixas` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `caixas` | `usuario_id` | `usuarios` | `RESTRICT` (D) | **Débito** — preferível `nullOnDelete` |
| `caixas` | `fechado_por` | `usuarios` | `RESTRICT` (D) — nullable | **Débito** — preferível `nullOnDelete` |
| `baixas_pagamento` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `baixas_pagamento` | `parcela_pagamento_id` | `parcelas_pagamento` | `cascadeOnDelete` | OK |
| `baixas_pagamento` | `caixa_id` | `caixas` | `nullOnDelete` | OK |
| `baixas_despesa` | `rede_id`, `empresa_id` | redes/empresas | `cascadeOnDelete` | OK |
| `baixas_despesa` | `parcela_despesa_id` | `parcelas_despesa` | `cascadeOnDelete` | OK |
| `baixas_despesa` | `caixa_id` | `caixas` | `nullOnDelete` | OK |
| `movimentos_caixa` | `caixa_id` | `caixas` | `cascadeOnDelete` | OK |
| `movimentos_caixa` | `baixa_pagamento_id` | `baixas_pagamento` | `nullOnDelete` | OK |
| `movimentos_caixa` | `baixa_despesa_id` | `baixas_despesa` | `nullOnDelete` | OK |

### Inconsistências conhecidas (top 3 — destaques)

1. **`vendas_produto.usuario_id` sem `nullOnDelete`** — apontado originalmente em FECH-018. Excluir um usuário com vendas registradas hoje falha por constraint `RESTRICT`.
2. **`*.rede_id` e `*.empresa_id` da Era 1 sem `cascadeOnDelete`** — apagar uma rede inteira pelo Eloquent quebra com erro de FK em `clientes`, `servicos`, `produtos`, `agendamentos`, `vendas_*`, `movimentos_estoque`, `usuarios`. Workaround atual: `softDeletes` (apenas marca `deleted_at`).
3. **FKs para `usuarios` em entidades operacionais (`atendente_id`, `caixas.usuario_id`)** — `RESTRICT` impede remover um usuário que tenha qualquer agendamento, venda ou caixa atrás. Dado que existe `softDeletes` em `usuarios`, o caminho atual é usar soft-delete. Hard-delete fica bloqueado.

## Consequências

### Positivas
- Padrão **escrito** para novas migrations evita repetir o mesmo erro silencioso.
- Auditoria deixa explícito **o que está bom e o que é débito** — não há "magia" sobre cascade no projeto.
- A maioria dos fluxos do dia-a-dia usa soft-delete, então as inconsistências são latentes (não bloqueantes).

### Negativas
- Hard-delete de uma rede ou empresa é hoje um procedimento manual (limpar dependências antes). Documentado como limitação.
- Refator das migrations da Era 1 fica como débito técnico explícito.

### Neutras
- A correção destrutiva (alterar FK existente) seria possível via migration `change()` com `dropForeign` + redefinição. Fora de escopo deste ADR — entra como item de roadmap quando o projeto for de fato operado em produção.
