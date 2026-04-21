# Decisoes Arquiteturais

Registro de decisoes tomadas durante o desenvolvimento.

---

## D001: Multi-tenant com single database

**Data:** 2026-03-22
**Decisao:** Usar single database com `rede_id` + `empresa_id` em vez de database/schema por tenant.
**Motivo:** Simplicidade para MVP. Menos complexidade operacional. Um unico banco e mais facil de manter, fazer backup e migrar.
**Trade-off:** Performance pode ser afetada em escala. Queries precisam de filtro em todas tabelas.
**Futuro:** Preparado para migrar para multi-database se necessario.

---

## D002: Sem pacote de tenancy externo

**Data:** 2026-03-22
**Decisao:** Implementar multi-tenant manualmente com Traits + Global Scopes + Middleware, sem usar spatie/multitenancy ou stancl/tenancy.
**Motivo:** Mais controle sobre o comportamento. Evita overhead de pacote. Projeto preparado para open source.
**Trade-off:** Mais codigo manual para manter.

---

## D003: Renomear Conta para Rede

**Data:** 2026-03-22
**Decisao:** Renomear conceito de "Conta" para "Rede" em todo o sistema.
**Motivo:** "Rede" reflete melhor o conceito de rede de negocios/franquia. Mais intuitivo para o usuario final.
**Impacto:** Migration de rename em todas as tabelas (conta_id → rede_id).

---

## D004: Atendente como campo do Usuario

**Data:** 2026-03-22
**Decisao:** Nao criar tabela `profissionais` separada. Usar campo `atende` no Usuario para indicar quem e atendente.
**Motivo:** Simplifica o modelo. Profissional e apenas um usuario que atende.
**Trade-off:** DATABASE.md define tabela `profissionais`, mas implementacao atual nao a criou.
**Revisao:** Avaliar se precisa de tabela separada quando houver necessidades especificas de profissional (especialidades, comissao, etc).

---

## D005: Modulos em app/Modules/

**Data:** 2026-03-22
**Decisao:** Estrutura modular em `app/Modules/{NomeModulo}/` com auto-load via ModuleServiceProvider.
**Motivo:** Separacao clara de responsabilidades. Cada modulo e autocontido. Facilita open source e manutencao.
**Trade-off:** Nao e o padrao Laravel convencional. Pode confundir devs acostumados com estrutura padrao.

---

## D006: Caixa com 1 aberto por empresa

**Data:** 2026-03-29
**Decisao:** Apenas 1 caixa pode estar aberto por vez por empresa.
**Motivo:** Simplifica controle financeiro. Evita inconsistencias.
**Trade-off:** Nao suporta multiplos PDVs simultaneos.
**Futuro:** Pode ser expandido para caixa por usuario se necessario.

---

## D007: Pagamento parcial via BaixaPagamento

**Data:** 2026-03-29
**Decisao:** Implementar pagamento parcial com tabela `baixas_pagamento`. Cada baixa registra uma parcela e atualiza `valor_pago`.
**Motivo:** Suporta fiado, parcelamento, pagamento misto.
**Mecanismo:** Quando valor_pago >= valor → status = "pago".

---

## D008: Categorias de produto padrao no onboarding

**Data:** 2026-03-29
**Decisao:** Criar 6 categorias padrao automaticamente no registro (Cabelo, Corpo, Rosto, Unhas, Consumiveis, Outros).
**Motivo:** Facilita onboarding para saloes de beleza (publico principal). Usuario ja tem categorias prontas.

---

## D009: Tudo em portugues

**Data:** 2026-03-22
**Decisao:** Todo o codigo (tabelas, models, controllers, campos, permissoes) em portugues.
**Motivo:** Preparado para open source voltado ao mercado brasileiro. Mais acessivel para desenvolvedores brasileiros.
**Trade-off:** Incomum para projetos open source internacionais. Pode limitar contribuicoes externas.

---

## D010: Fiado nao e forma de pagamento

**Data:** 2026-04-19
**Decisao:** Remover `Fiado` do enum `FormaPagamento`. Fiado passa a ser uma **condicao de venda** ("a_vista" / "a_prazo"), nao uma forma de pagamento.
**Motivo:** O modelo anterior permitia combinacao invalida `forma=fiado + status=pago`, gerando entrada fantasma no caixa e inconsistencias em contas a receber. Praticas consolidadas em ERPs (Odoo, Dynamics 365, ERP M8, SIGE Cloud) separam os conceitos: "forma de pagamento" e preenchida apenas quando o dinheiro entra; "fiado" e a decisao comercial de receber depois.
**Impacto:**
- `FormaPagamento` enum: so `pix/dinheiro/cartao`
- `pagamentos.forma_pagamento` e nullable — NULL quando a venda e a prazo
- Form de venda agora tem campo "Condicao de Pagamento" (a_vista / a_prazo); forma so aparece no a_vista
- Forma real do fiado fica registrada apenas na BaixaPagamento quando o cliente paga
**Trade-off:** Remove flexibilidade de marcar fiado "direto como pago" — mas isso era um anti-padrao que gerava inconsistencia.
