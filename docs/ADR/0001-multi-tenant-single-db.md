# ADR-0001 — Multi-tenant single-DB com `rede_id`

## Status

Aceito — abril/2026.

## Contexto

O Meu Negócio é um SaaS B2B onde cada cliente é uma rede (uma marca, com 1+ empresas). Os dados de uma rede precisam estar **completamente isolados** dos dados de outra rede, em todos os módulos (clientes, vendas, financeiro, caixa, etc.).

Três estratégias clássicas de tenancy foram avaliadas:

1. **Database per tenant** — uma base MySQL por rede.
2. **Schema per tenant** — uma base, vários schemas (um por rede).
3. **Single DB com discriminador (`rede_id`)** — uma base, uma tabela por entidade, coluna `rede_id` em cada linha + filtro automático.

Como projeto de portfólio rodando em Docker em uma única máquina, com escala de leitura/escrita modesta e prioridade de demonstração de padrões arquiteturais, a complexidade operacional pesa mais que o ganho de isolamento físico.

## Decisão

Adotamos **single-DB com `rede_id`**:

- Toda tabela tenant-aware tem coluna `rede_id` com FK para `redes`.
- O isolamento é aplicado por **Eloquent Global Scope** via `RedeTrait`, embutido no `BaseModel`.
- Modelos transacionais por empresa (Agendamento, Venda, Caixa, etc.) também recebem `EmpresaTrait` para filtrar por `empresa_id`, com bypass para usuários `Admin` quando aplicável.
- Middlewares (`verificar.rede`, `verificar.empresa`) garantem que cada request entra com o tenant resolvido em sessão antes de tocar Eloquent.

## Consequências

### Positivas
- **Operação simples:** uma única base para subir, migrar, fazer backup. Compatível com Docker single-node.
- **Schema único:** mudanças de schema atingem todos os tenants em uma migration.
- **Joins cross-tabela triviais:** todas as tabelas convivem na mesma base, sem federação.
- **Pattern visível:** o `RedeTrait` e o `BaseModel` deixam o pattern explícito para qualquer leitor do código.

### Negativas
- **Risco de vazamento por bug:** um esquecimento (`withoutGlobalScopes`, `DB::table` cru) pode expor dados entre redes. Mitigado por testes de isolamento (ver `tests/Feature/MultiTenant/IsolamentoTest`).
- **Sem isolamento físico:** uma rede pesada pode degradar performance das outras (não há quotas por tenant).
- **Backup não-granular:** restaurar dados de **uma** rede exige queries seletivas; não basta copiar a base.

### Neutras
- Escalar verticalmente (CPU/RAM) cobre o roadmap previsto. Se a base crescer ao ponto de exigir sharding, o caminho natural é migrar para schema-per-tenant ou DB-per-tenant — refator pesado, mas mecânico.
