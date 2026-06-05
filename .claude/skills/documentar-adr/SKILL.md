---
name: documentar-adr
description: "Cria um ADR (Architecture Decision Record) no padrao docs/ADR/ do Meu Negocio e atualiza o indice. Use quando uma decisao arquitetural/tecnica relevante for tomada (escolha de abordagem, trade-off, novo padrao, mudanca de escopo de tenancy), ou quando pedirem 'documenta essa decisao', 'cria um ADR', 'registra o porque'."
argument-hint: "<titulo curto da decisao>"
---

# Documentar ADR — Meu Negocio

ADRs registram **por que** uma decisao foi tomada (contexto + trade-offs), nao so o que. Ficam em
`docs/ADR/`. Ja existem 7 (multi-tenant single-DB, Titulo+Parcela+Baixa, estrutura modular, BaseModel
+ traits, caixa diario, FKs, assinatura pro-rata).

## Passos
1. **Siga o formato real**: abra `docs/ADR/README.md` (indice) e um ADR existente (ex.:
   `docs/ADR/0001-*.md`) e copie a estrutura e o tom exatos — nao invente um template.
2. **Numere em sequencia**: proximo numero livre, nome `NNNN-titulo-em-kebab.md`.
3. **Escreva** as secoes do padrao do projeto — tipicamente: Status, Contexto, Decisao,
   Consequencias (positivas/negativas/trade-offs) e Alternativas consideradas. Conciso, portugues,
   honesto sobre os custos da decisao.
4. **Status**: `Proposto`, `Aceito`, ou `Substituido por NNNN`.
5. **Atualize o indice** `docs/ADR/README.md` com a nova linha.
6. Se a decisao muda arquitetura/padrao, alinhe tambem `CLAUDE.md` e as `.claude/rules/` afetadas
   (a skill `checklist-pre-pr` lembra disso).

## Saida
O arquivo `docs/ADR/NNNN-*.md` criado, o indice atualizado, e um resumo de 1 linha da decisao.
