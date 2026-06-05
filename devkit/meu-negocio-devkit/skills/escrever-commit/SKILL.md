---
name: escrever-commit
description: "Escreve mensagem(ns) de commit no padrao do Meu Negocio (`tipo(modulo): mensagem`, em portugues, imperativo). Use quando o usuario pedir para commitar, pedir uma 'mensagem de commit', ou quando for organizar mudancas em commits — agrupando por tema em vez de um commitao."
argument-hint: "[escopo/tema] (opcional)"
---

# Escrever commit — Meu Negocio

Padrao: `tipo(modulo): resumo no imperativo`. Mensagem clara conta o **que** e o **porque**, nao o
como (o diff ja mostra o como).

## Formato
- **tipo**: `feat`, `fix`, `refactor`, `docs`, `chore`, `test`.
- **modulo**: a area afetada (`pagamento`, `agenda`, `caixa`, `automacao`, `tenant`, ...).
- **resumo**: imperativo, minusculo, sem ponto final, conciso. Ex.:
  - `feat(pagamento): adiciona baixa parcial por parcela`
  - `fix(caixa): corrige saldo ao reabrir caixa retroativo`
  - `refactor(automacao): migra conhecimento do .ai para rules lazy`
- **corpo** (opcional): explique o porque / contexto / decisao quando nao for obvio.

## Como montar
1. Veja o que esta para commitar: `git status` e `git diff --staged` (ou `git diff`).
2. **Agrupe por tema**: se o diff mistura assuntos, proponha varios commits coesos (um por tema),
   nao um commitao. Liste o que vai em cada um.
3. So commite quando o usuario pedir. Se on `main`, crie branch antes (regra do projeto/harness).
4. O ambiente injeta o trailer `Co-Authored-By` automaticamente — nao o adicione manualmente.

## Saida
A(s) mensagem(ns) prontas. Se for executar, mostre os `git add` por tema e os `git commit` antes de
rodar.
