---
name: fluxo-git
description: "Mecanica do git no Meu Negocio: de onde partir, como nomear a branch, como agrupar commits e como mergear (que aqui significa publicar em producao). Use ao INICIAR trabalho novo, ao perguntar 'de onde eu parto?', 'qual nome dou pra branch?', 'como subo isso pra producao?', 'ja posso mergear?'. NAO e a porta de qualidade antes do PR — para 'terminei, esta pronto pra revisao?' use a skill checklist-pre-pr."
---

# Fluxo git — Meu Negocio

A regra que organiza todo o resto: **a `main` publica em producao** (Railway, deploy por git). Ela
nao e uma branch de integracao onde se acumula trabalho — e o que os usuarios estao rodando agora.
Todo o fluxo abaixo existe para que nada chegue la sem passar pela porta.

## 1. Comecar — sempre da main atualizada

Antes de escrever a primeira linha:

```bash
git checkout main && git pull
git checkout -b tipo/descricao-curta
```

**Por que sempre da main, e sempre atualizada:** partir de uma branch antiga faz o PR carregar
commits que ja estao la, polui o diff da revisao e cria conflito no merge. Partir de outra feature
branch acopla as duas — se a primeira nao for aprovada, a segunda vai junto.

O atalho `/nova-feature <descricao>` faz esses tres passos e ja confere se o working tree esta limpo.

### Nome da branch

`tipo/descricao-curta`, em portugues, sem acento, palavras separadas por hifen. O `tipo` e o mesmo
vocabulario dos commits:

| Tipo | Quando | Exemplo |
|---|---|---|
| `feat/` | comportamento novo | `feat/renovar-teste-gratuito` |
| `fix/` | corrigir algo quebrado | `fix/eager-loading-timeline-caixa` |
| `refactor/` | mudar a forma, nao o comportamento | `refactor/extrai-parcelamento` |
| `perf/` | desempenho | `perf/assets-cache-frankenphp` |
| `docs/` | so documentacao | `docs/adr-licenca-por-empresa` |
| `chore/` | build, CI, automacao | `chore/automacao-fluxo-e-evals` |
| `test/` | so cobertura | `test/agenda-conflito` |

Descreva o **resultado**, nao a tarefa: `feat/renovar-teste-gratuito`, nao `feat/mexer-na-assinatura`.

### Uma branch, um assunto

Se no meio do caminho aparecer um bug sem relacao com a feature, **nao emende**: anote, termine o que
esta fazendo, e trate depois na propria branch. PR que faz duas coisas nao pode ser revertido pela
metade — e reverter e a unica ferramenta rapida quando algo quebra em producao.

## 2. Durante — commits que contam a historia

Agrupe por tema, nao por arquivo nem por sessao de trabalho. A skill `escrever-commit` cuida da
mensagem (`tipo(modulo): mensagem`, em portugues, imperativo). O padrao que este repo segue e separar:

```
feat(assinatura): renova o teste vencido e restringe a assinatura ao Admin
test(assinatura): cobre a renovacao do teste e a visibilidade por perfil
docs(assinatura): registra a renovacao do teste no ADR-0013
```

O corpo do commit explica **por que**, nao o que — o diff ja mostra o que.

O hook `guard-branch` recusa `git commit` na main. Se ele disparar, voce esqueceu de criar a branch:
`git checkout -b tipo/descricao` leva as mudancas junto.

## 3. Antes do PR — a porta

```
/pre-pr
```

Roda Pint, PHPStan e a suite no container, exatamente como o CI. Verde aqui e verde la — e mais
rapido descobrir agora. Se tocou tela, a `validar-implementacao` cobre tambem o smoke; **se tocou JS,
clique de verdade no navegador**: teste HTTP nao pega handler que nao dispara.

## 4. Abrir o PR

```bash
git push -u origin <branch>
gh pr create --base main
```

O template (`.github/pull_request_template.md`) ja pergunta o que importa. Preencha o **risco** com
honestidade: e ele que decide se o merge acontece agora ou depois do expediente.

## 5. Mergear — e publicar

Espere o CI:

```bash
gh pr checks <numero> --watch
```

So entao:

```bash
gh pr merge <numero> --merge --delete-branch
```

**`--merge`, nao `--squash`:** este repo preserva os commits individuais (veja `git log --merges`), e
a separacao feat/test/docs so tem valor se sobreviver ao merge.

**Confirme com o usuario antes de mergear**, salvo autorizacao explicita para aquele PR. Merge aqui
nao e integracao: e deploy.

## 6. Depois

`--delete-branch` limpa a remota e a local. Se o repo estiver configurado (`bin/configurar-github.sh`),
o GitHub tambem apaga sozinho. Passivo acumulado sai com `bash bin/limpar-branches.sh`.

Volte para a base antes do proximo trabalho:

```bash
git checkout main && git pull
```

## Situacoes que aparecem

**"Ja comecei a mexer na main."** Nada perdido: `git checkout -b tipo/descricao` leva as alteracoes
nao commitadas junto.

**"A main andou enquanto eu trabalhava."** `git checkout main && git pull` e, na sua branch,
`git merge main`. Resolva conflito com a suite rodando. Se a protecao estiver ativa, o GitHub exige
isso antes do merge (`strict: true`).

**"O PR ficou grande demais."** Grande de verdade e o que mistura assuntos. Um PR de 500 linhas todas
sobre a mesma coisa revisa-se bem; um de 80 linhas que mexe em tres modulos, nao.

**"Preciso corrigir producao agora."** Mesmo fluxo, `fix/`, e diga no PR que e urgente. O caminho
completo leva minutos; pular a porta e o que custa horas.

## Veja tambem

- `checklist-pre-pr` — a porta completa, passo a passo.
- `escrever-commit` — a mensagem no padrao do repo.
- `validar-implementacao` — provar que funciona antes de dizer que esta pronto.
- `bin/doctor.sh` — diagnostico do ambiente (hooks ativos? main protegida? branches obsoletas?).
