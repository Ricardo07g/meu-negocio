# Evals da automação

A automação deste repo é tratada como código: **medida, não só escrita**. Uma skill não vale pelo que
está escrito nela — vale por mudar o resultado de um agente resolvendo um problema real deste
projeto. Esta pasta existe para que essa afirmação seja verificável por quem lê, e não apenas
declarada.

Três níveis, do mais barato ao mais caro. Cada um responde a uma pergunta diferente.

| Nível | Pergunta | Custo | Onde roda |
|---|---|---|---|
| **1 — Integridade** | A automação está íntegra? Rules apontam para arquivos que existem? Hooks funcionam? | zero | CI, a cada push |
| **2 — Triggering** | A skill certa dispara para o pedido certo — e fica quieta quando não é o caso? | centavos | sob demanda |
| **3 — A/B** | A skill melhora o resultado, comparada ao mesmo agente sem ela? | dólares | sob demanda |

## Nível 1 — Integridade (determinístico)

Vive na suíte de testes, não aqui: [`tests/Feature/AutomacaoIntegridadeTest.php`](../tests/Feature/AutomacaoIntegridadeTest.php).

```bash
docker exec meu-negocio-app php artisan test --filter=AutomacaoIntegridadeTest
```

Verifica que toda rule declara `paths:`, que **esse `paths:` casa com arquivo real** (o modo de falha
mais traiçoeiro: a rule existe, parece saudável e nunca carrega), que os arquivos citados nas rules
não sumiram do repo, que toda skill tem `name` batendo com o diretório e uma `description` que diz
*quando* usar, e que todo hook registrado existe, é executável e não depende só de `jq`.

Esse último veio de um caso real: os hooks liam o stdin com `jq`, que não vem instalado no macOS.
Sem ele, cada hook caía no `exit 0` e saía calado — a proteção do `.env` ficou desligada por meses
sem nenhum sinal. Automação que falha em silêncio é pior que automação ausente.

O `bin/doctor.sh` é a versão interativa disso, para o ambiente local.

## Nível 2 — Triggering

```bash
bash evals/bin/triggering.sh                  # 1 execução por caso (rápido)
bash evals/bin/triggering.sh --runs 3         # 3 execuções: mede a variância
bash evals/bin/triggering.sh --caso branch    # filtra por substring
bash evals/bin/triggering.sh --modelo opus
```

Monta o catálogo real de `description`s e pergunta ao modelo qual skill ele invocaria para cada
pedido de [`cenarios/triggering.tsv`](cenarios/triggering.tsv). Nada é executado — só a **decisão** é
medida, o que torna o eval rápido e sem efeito colateral.

O arquivo de casos tem três famílias, e a terceira é a que dá honestidade ao número:

- **caminhos óbvios** — "escreve testes pro Estoque" → `gerar-teste-model`;
- **pares que se confundem** — `validar-implementacao` x `checklist-pre-pr`, `depurar` x `revisar-codigo`;
- **distratores** — "qual a versão do PHP?" → `nenhuma`. Sem eles, o eval premiaria a skill que
  dispara sempre, que é o pior tipo de skill.

A saída separa **falso positivo** (disparou sem precisar) de **falso negativo** (ficou quieta quando
devia agir): os dois pedem correções opostas na `description`, e uma taxa agregada esconderia isso.

Cada execução grava um `.tsv` em `resultados/`. Abaixo de 70% de acerto o script sai com erro — piso
conservador para sinalizar `description` ambígua.

### Por que `--runs` existe

A escolha do modelo não é determinística, e isso quase me enganou. Duas execuções seguidas da **mesma
configuração** deram 28/30 e 27/30 — mas com erros *diferentes*: casos que falharam numa passaram na
outra. Se eu tivesse olhado só o primeiro número, teria "corrigido" ruído acreditando estar corrigindo
uma `description`.

Por isso o resultado é reportado em três categorias, não duas:

| | Significado | O que fazer |
|---|---|---|
| **✓ acerta sempre** | a description discrimina bem | nada |
| **✗ erra sempre** | a description está errada ou falta gatilho | reescrever |
| **~ instável** | acerta às vezes | **este é o achado**: duas skills se sobrepõem, ou o caso é genuinamente ambíguo |

A instabilidade é informação, não um número a arredondar. `cria a tabela de fornecedores` oscila
entre `criar-migration` e `scaffold-modulo` porque o pedido é mesmo ambíguo — depende de a entidade
já existir. Um caso assim se resolve desambiguando a `description` ou aceitando a ambiguidade de
forma consciente; o que não dá é fingir que ela não existe.

### Linha de base atual

3 execuções por caso, modelo Sonnet — resultado bruto em
[`resultados/exemplo-triggering.tsv`](resultados/exemplo-triggering.tsv):

| | |
|---|---|
| Decisões corretas | **80/90 (88%)** em 30 casos |
| Erram sempre | **0** |
| Instáveis | **7** |
| Falso positivo | 2 |
| Falso negativo | 6 |

Zero casos errando sempre significa que nenhuma `description` está simplesmente errada. Os 7
instáveis são o backlog real, e cada um diz uma coisa diferente:

- **`pode commitar isso?` (1/3)** e **`implementei o filtro novo, funciona?` (1/3)** — pedidos curtos
  e coloquiais. As descriptions cobrem a intenção formal ("gerar mensagem de commit", "validar
  implementação") e perdem a forma como a pessoa realmente fala.
- **`quantos testes temos hoje?` (1/3)** — o modelo respondeu `testar`, que é *slash command*, não
  skill. Falso positivo que revela um limite do próprio eval: o catálogo mostra só skills, mas o
  agente real também enxerga comandos. Vale medir os dois juntos.
- **`ta bom esse service?` (2/3)** — oscila entre `revisar-codigo` e `padroes-projeto`. Sobreposição
  legítima: revisar exige conhecer o padrão.
- **`cria a tabela de fornecedores` (2/3)** — ambiguidade do pedido, não da description: depende de a
  entidade já existir.

Nenhum foi corrigido ainda. Registrar o que se sabe e ainda não se resolveu é parte do método —
esconder as instabilidades para publicar um número redondo seria o oposto do que esta pasta existe
para fazer.

### O loop na prática

Esta pasta nasceu junto com a skill `fluxo-git`, e o primeiro run já cobrou o preço: `terminei a
feature, quero abrir o PR` passou a cair em `fluxo-git`, quando a resposta certa é `checklist-pre-pr`
— a skill nova tinha invadido o território da existente. A correção foi uma frase na `description`
(*"NÃO é a porta de qualidade antes do PR — para isso use checklist-pre-pr"*), e o caso voltou a
passar. Sem o eval, essa colisão só apareceria em uso, como uma skill que "às vezes faz a coisa
errada".

## Nível 3 — A/B com grader

O caro, e o único que responde "a skill *melhora* alguma coisa?".

Para cada cenário de [`cenarios/ab/`](cenarios/ab/), dois agentes resolvem a **mesma** tarefa sobre
**este** código — um com a skill, outro sem (baseline) — e um grader pontua os dois contra critérios
objetivos, definidos antes da execução e com peso explícito. Os cenários saíram de defeitos que
realmente aconteceram aqui, não de exercícios inventados:

- [`renovar-trial.md`](cenarios/ab/renovar-trial.md) — um botão que passou por 339 testes verdes e
  nunca funcionou;
- [`tenancy-vazamento.md`](cenarios/ab/tenancy-vazamento.md) — `withoutGlobalScopes()` numa listagem.

Cada cenário define um **critério eliminatório**: no primeiro, declarar "está tudo certo" com o botão
quebrado reprova independentemente do resto. Sem isso, um agente prolixo pontua bem sem ter resolvido
nada.

O runner completo (dois agentes por cenário, N execuções, grader) ainda não está automatizado — os
cenários e a rubrica estão versionados e são executáveis à mão hoje. Preferi registrar isso a
publicar um script que não roda de ponta a ponta.

### O que a rodada anterior mostrou

Iteração 1, 6 cenários, modelo Sonnet, 1 execução por configuração:

| Métrica | Com skill | Sem skill |
|---|---|---|
| Pass rate | 100% | 90% |
| Tempo médio | ~238s | ~316s |
| Tokens médios | ~59k | ~74k |

Leitura honesta: com uma execução por configuração, isso é **sinal de direção, não benchmark**. O
baseline já forte (90%) é o dado mais interessante — o ganho estrutural vem das rules lazy e do
`CLAUDE.md`, que qualquer agente herda neste repo; a skill acrescenta consistência e eficiência
(mais rápida e mais barata, por direcionar às dimensões certas). Uma skill que só reproduz o que o
baseline já faz não se justifica, e é isso que este nível existe para revelar.

## Adicionando um caso

**Triggering:** uma linha em `cenarios/triggering.tsv` (`prompt` TAB `skill-esperada`). Se o pedido
não deve acionar nada, use `nenhuma` — distratores valem tanto quanto acertos.

**A/B:** um `.md` em `cenarios/ab/` com preparo, tarefa, tabela de critérios com pesos e qual deles é
eliminatório. Prefira defeitos que já aconteceram: eles medem o que este projeto realmente erra.
