---
name: revisar-codigo
description: "Revisa codigo do Meu Negocio (o diff atual por padrao) sob a otica de qualidade, SOLID, padroes do projeto e isolamento multi-tenant, apontando correcoes acionaveis por severidade. Use antes de concluir uma tarefa nao-trivial, quando o usuario pedir 'revisa/da uma olhada/code review/ta bom?', ou apos uma alteracao relevante — mesmo sem a palavra 'review'."
---

# Revisar codigo — Meu Negocio

Revisao critica e construtiva, focada no que mudou. Objetivo: pegar problema antes do teste/PR e
manter a base coerente. Por padrao revise o **diff** (`git diff` / `git diff --staged`); se o usuario
indicar um alvo, use-o.

## Dimensoes (nesta ordem)
1. **Padroes do projeto** (skill `padroes-projeto`): controller fino com `authorize()`, `try/catch` +
   `tratarErro`, Service/Action no lugar de regra no controller, `SalvarXxxRequest` e `XxxData`
   unificados, Model em BaseModel com secoes ASCII, `comEmpresaDeCriacao` em escrita multi-empresa,
   `DB::transaction` so na Service.
2. **Tenancy e seguranca** (`.claude/rules/multi-tenant-seguranca.md`): nenhum vazamento
   `rede_id`/`empresa_id`, Policy registrada em `AppServiceProvider`, `authorize()` nas acoes
   mutaveis, sem `withoutGlobalScope`/`DB::` cru injustificado, sem mass-assignment de tenant. Para
   rigor, dispare `/auditar-tenancy` ou o subagente `tenancy-security-reviewer`.
3. **Qualidade/SOLID**: responsabilidade unica, duplicacao, nomes claros (portugues), early-return,
   acoplamento, metodos longos que pedem extracao.
4. **Testes**: o caminho novo esta coberto? Falta caso de borda, isolamento ou 403? (skill
   `gerar-teste-model`).
5. **UI** (se Blade): `.claude/rules/ui-duralux.md` — partial `_form`, busca AJAX, badges, icone
   Feather por classe (nao `data-feather`).

## Delegacao
Para revisao arquitetural profunda, acione o subagente **laravel-senior-architect**; para tenancy, o
**tenancy-security-reviewer**. Para muitos arquivos, dispare em paralelo.

## Saida
Lista priorizada por severidade — **Critico** / **Importante** / **Sugestao** — cada item com
`arquivo:linha`, o problema e a correcao proposta. Aplique os triviais/obvios e liste o resto para
decisao. Nao invente problema: se esta bom, diga que esta bom.
