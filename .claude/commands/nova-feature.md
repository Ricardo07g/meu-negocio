---
description: Comeca um trabalho novo a partir da main atualizada, criando a branch no padrao do repo.
argument-hint: "[tipo/]descricao-curta  (ex.: relatorio-de-comissao ou fix/saldo-do-caixa)"
allowed-tools: Bash(git status:*), Bash(git checkout:*), Bash(git pull:*), Bash(git stash:*), Bash(git branch:*), Bash(git log:*)
---

Inicie um trabalho novo no padrao do repo. Argumento recebido: `$ARGUMENTS`

Siga a skill `fluxo-git`. Passos:

1. **Confira o estado.** `git status --porcelain` e `git branch --show-current`.
   - Working tree sujo: **pare e pergunte** o que fazer com as alteracoes (commitar na branch atual,
     `git stash`, ou levar junto para a branch nova). Nunca descarte trabalho sem confirmar.
   - Ja numa branch de trabalho com commits nao mergeados: avise antes de sair dela.

2. **Volte para a base atualizada.** `git checkout main && git pull`.
   Partir da main de ontem faz o PR carregar commits alheios e cria conflito no merge.

3. **Derive o nome da branch** de `$ARGUMENTS`:
   - Se ja vier com prefixo valido (`feat/`, `fix/`, `refactor/`, `perf/`, `docs/`, `chore/`, `test/`),
     use como esta.
   - Se nao, **infira o tipo** pela descricao (algo quebrado -> `fix/`; comportamento novo -> `feat/`;
     so documentacao -> `docs/`; CI/automacao -> `chore/`) e diga qual escolheu.
   - Normalize: minusculas, sem acento, palavras com hifen, curto e sobre o **resultado**.
   - Sem argumento: pergunte em uma frase o que sera feito, e proponha o nome.

4. **Crie a branch.** `git checkout -b <nome>`.

5. **Feche com o contexto**, em duas ou tres linhas: a branch criada, de onde partiu
   (`git log --oneline -1`) e o proximo passo concreto do trabalho pedido — nao um checklist generico.

Nao comece a implementar nada neste comando: ele so prepara o terreno.
