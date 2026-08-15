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
4. **Testes** (`.claude/rules/testes-por-feature.md`): nao pergunte "tem teste?", pergunte **quais a
   regua exige**. Tenant-aware pede isolamento; rota mutavel pede o 403; dinheiro pede o estorno;
   regra de recusa pede um teste por recusa. Suite verde nao e cobertura — verde e facil quando nao
   se escreveu nada. Para escrever, skill `gerar-teste-model`.
5. **JS de tela** (`.claude/rules/javascript-telas.md`) — **dimensao propria, nao subitem de UI**: e o
   unico codigo que nem Pint, nem PHPStan, nem a suite HTTP enxergam. Confira:
   - `Swal.fire().then()` lendo **so** `isConfirmed` — o bundle do Duralux resolve com `{value: true}`,
     entao o botao abre o modal e nao faz nada, **sem erro no console**. Ja quebrou dois botoes aqui.
   - interpolacao de texto em aspas simples (`'{{ ... }}'`) no lugar de `@json(...)`.
   - `form.submit()` onde se esperava passar pelos listeners (`requestSubmit()`).
   - conteudo injetado por AJAX sem re-ligar handlers.
   Mudou JS? Exija a evidencia do **clique real**, nao do teste HTTP.
6. **UI** (se Blade): `.claude/rules/ui-duralux.md` — partial `_form`, busca AJAX, badges, icone
   Feather por classe (nao `data-feather`).

## Delegacao
Para revisao arquitetural profunda, acione o subagente **laravel-senior-architect**; para tenancy, o
**tenancy-security-reviewer**. Para muitos arquivos, dispare em paralelo.

## Saida
Lista priorizada por severidade — **Critico** / **Importante** / **Sugestao** — cada item com
`arquivo:linha`, o problema e a correcao proposta. Aplique os triviais/obvios e liste o resto para
decisao. Nao invente problema: se esta bom, diga que esta bom.
