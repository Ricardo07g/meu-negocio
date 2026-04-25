---
name: Project Inconsistencies — Audit 2026-04-25
description: Inconsistências entre CLAUDE.md/docs e código real, descobertas em auditoria de fechamento de escopo
type: project
---

Auditoria de 2026-04-25 (documento `docs/FECHAMENTO_PORTFOLIO.md`) detectou as seguintes divergências CLAUDE.md/.ai vs código real:

1. **Calendar lib**: docs falam FullCalendar 6, código usa `@toast-ui/calendar` ^2.1.3 (`package.json`, `resources/js/calendar.js`).
2. **Papéis**: `PapelEnum` declara 7 papéis, `PermissaoSeeder` cria apenas 2 (Admin, Profissional). Criar usuário com papel "Gerente" passa validação mas explode no Spatie.
3. **`tem_relatorios`**: flag de plano sem ponto de uso real (não há módulo Relatórios). Recomendado CORTAR.
4. **`.env.example`**: incompatível com Docker setup real (SQLite + locale en, contra MySQL + pt_BR).
5. **README**: ainda é o boilerplate genérico do Laravel (não-aceitável para portfólio).
6. **Auth**: marcado como "parcial" mas sem detalhar o gap — falta reset de senha completo (rota, controller, mail, view).
7. **Papel** marcado como "parcial" mas commit `dc04798` o completou.
8. **`RegistraAtividade`**: aplicado só em 3 modelos (Agendamento, Pagamento, Caixa). Despesa, Venda*, MovimentoEstoque ficaram fora.
9. **Policies**: VendaProduto, ParcelaPagamento, ParcelaDespesa, BaixaPagamento, BaixaDespesa, MovimentoCaixa não têm Policy.
10. **Tenant**: `RedeService::atualizar/alterarPlano`, `AtualizarRedeData`, `RedePolicy`, `PlanoPolicy` órfãos (nenhuma rota usa).

**Why:** o projeto evoluiu rápido em mar/abr 2026 e a documentação interna (CLAUDE.md, `.ai/`) ficou para trás. Para portfólio, isso é red flag de "atenção a detalhes".

**How to apply:**
- Antes de afirmar qualquer coisa sobre o estado do projeto, validar contra `docs/FECHAMENTO_PORTFOLIO.md` e código.
- Quando o backlog FECH-XXX for executado, marcar como concluído e atualizar este memo.
- Se alguma inconsistência for resolvida diferente do recomendado, registrar a decisão real.
