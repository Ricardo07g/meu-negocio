# Contribuindo com o Meu Negócio

Obrigado pelo interesse em contribuir. Este projeto é peça de portfólio e segue convenções enxutas para manter o repositório claro e fácil de navegar.

## Como rodar localmente

O setup completo (Docker, migrations, seeders e credenciais demo) está documentado no [README](README.md#setup-local-com-docker). Em resumo: `cp .env.example .env`, `docker compose up -d`, `composer install`, `php artisan key:generate` e `php artisan migrate:fresh --seed`. Acesso em [http://localhost:8080](http://localhost:8080).

## Padrão de commits

Mensagens seguem o estilo [Conventional Commits](https://www.conventionalcommits.org/), em português, no infinitivo, no padrão `tipo(modulo): descricao curta`. Tipos usados no histórico: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`.

Exemplos reais do `git log`:

- `feat(auth): adiciona rate limit em login e registro (FECH-019)`
- `refactor(models): remove secoes ASCII art vazias (FECH-017)`
- `docs: reescreve README como peca de portfolio (FECH-001)`

Quando o commit fizer parte de um item do backlog em [`docs/FECHAMENTO_PORTFOLIO.md`](docs/FECHAMENTO_PORTFOLIO.md), referencie o ID (`FECH-XXX`) no final.

## Fluxo de trabalho (branches e PR)

> **A `main` publica em produção.** O deploy no Railway acontece por git: o que entra na `main` vai ao ar. Ela não é uma branch de integração onde se acumula trabalho — é o que os usuários estão rodando agora. Todo o fluxo abaixo existe por causa disso.

### 1. Comece sempre da `main` atualizada

```bash
git checkout main && git pull
git checkout -b tipo/descricao-curta
```

Partir de uma branch antiga faz o PR carregar commits que já estão lá e polui a revisão; partir de outra feature branch acopla as duas.

### 2. Nomeie no padrão `tipo/descricao-curta`

Em português, sem acento, com hífen, descrevendo o **resultado** (`feat/renovar-teste-gratuito`, não `feat/mexer-na-assinatura`). Os tipos são os mesmos dos commits: `feat`, `fix`, `refactor`, `perf`, `docs`, `chore`, `test`.

**Uma branch, um assunto.** Se aparecer um bug sem relação no meio do caminho, anote e trate depois: PR que faz duas coisas não pode ser revertido pela metade — e reverter é a ferramenta mais rápida quando algo quebra em produção.

### 3. Antes de abrir o PR

Rode a porta local (mesmos passos do CI):

```bash
docker compose exec app composer test
docker compose exec app vendor/bin/phpstan analyse --no-progress
docker compose exec app vendor/bin/pint --test
```

Se mexeu em **JS de tela**, clique de verdade no navegador: teste HTTP não pega handler que não dispara.

### 4. Abra, espere o CI, mergeie

```bash
git push -u origin <branch>
gh pr create --base main          # o template já pergunta o que importa
gh pr checks <n> --watch
gh pr merge <n> --merge --delete-branch
```

`--merge` e não `--squash`: o histórico preserva os commits individuais, e a separação `feat`/`test`/`docs` só tem valor se sobreviver ao merge.

### Configuração do repositório

```bash
bash bin/configurar-github.sh --check   # mostra o estado
bash bin/configurar-github.sh           # aplica: PR obrigatório, CI obrigatório, branch deletada ao mergear
bash bin/limpar-branches.sh             # remove branches remotas já mergeadas
bash bin/doctor.sh                      # diagnóstico do ambiente e da automação
```

## Como rodar testes e lint

```bash
docker compose exec app composer test
docker compose exec app vendor/bin/pint --test
```

Ambos precisam passar antes do PR. Se você tocou em arquivos PHP, rode também `docker compose exec app vendor/bin/pint` (sem `--test`) para auto-corrigir formatação.

## Automação com Claude Code (opcional, acelera o fluxo)

Quem desenvolve com o Claude Code tem atalhos prontos em `.claude/` (detalhes em [`docs/AUTOMACAO.md`](docs/AUTOMACAO.md)):

- **Slash commands**: `/nova-feature <descrição>` (cria a branch a partir da main atualizada), `/testar [filtro]`, `/migrar`, `/pre-pr` (porta de qualidade) e `/auditar-tenancy`.
- **Skills**: `fluxo-git` conduz o ciclo branch → commits → PR → merge; `validar-implementacao` valida uma feature ponta-a-ponta (testes do módulo + Pint + PHPStan + smoke); `revisar-codigo`, `depurar`, `criar-migration`, `adicionar-permissao` e outras guiam tarefas no padrão do projeto.
- **Knowledge lazy**: regras de domínio em `.claude/rules/` carregam sozinhas conforme o arquivo que você edita.
- **Hooks**: formatam o PHP editado, conferem o Blade, e recusam commit na `main` ou edição no `devkit/` (que é gerado).

Não é obrigatório — `composer test` + `pint` cobrem o essencial — mas usar `/pre-pr` antes de abrir o PR reproduz localmente a porta do CI.

## Padrões de código

O projeto tem convenções fortes e consistentes — siga-as para manter a base coerente. O módulo `app/Modules/Produto/` é a referência mais completa.

### Formatação (garantida pelo `pint.json`)

A formatação é versionada em [`pint.json`](pint.json) e aplicada pelo Laravel Pint. Não formate "no olho": rode `vendor/bin/pint` e deixe a ferramenta decidir. O preset é `laravel`, com estas regras adicionais:

- **`declare(strict_types=1)`** no topo de **todo arquivo de classe** (`app/`, `database/`, `routes/`, `config/`, `tests/`). Views Blade e o _skeleton_ (`bootstrap/`, `public/`, `lang/`) ficam de fora.
- **Imports do mesmo namespace agrupados com chaves**: `use App\Modules\Despesa\Models\{Despesa, ParcelaDespesa};`.
- **Imports ordenados alfabeticamente** e **sem imports não usados**.

> Atenção ao hook/CI: como imports não usados são removidos automaticamente, **adicione o import e o seu uso na mesma alteração** — senão a ferramenta remove o import "órfão" antes de você usá-lo.

### Arquitetura por camadas

- **Controller fino**: apenas request/response + `$this->authorize(...)`, delegando a regra de negócio para Service/Action. Conversão de enums/datas e montagem de DTO podem virar métodos privados (`montarDados`, `processarVenda`) para manter o método de rota curto.
- **Service** concentra a regra de negócio do módulo; **Action** isola uma operação de escrita complexa e reaproveitável.
- **Transações/rollback ficam nas Services**, via `DB::transaction(fn () => ...)`. **Nunca** use `DB::` direto no controller.
- **Request unificado** `SalvarXxxRequest` (distingue criar/editar por `isMethod('post')`; permissão por `routeIs(...)` quando necessário) e **DTO unificado** `XxxData` (spatie/laravel-data).
- **Model** estende `App\Models\BaseModel` e organiza o corpo em seções ASCII art (RELATIONS, ACESSORS, MUTATORS, SCOPES, METHODS); casts via `casts(): array`.
- **Policy** registrada em `app/Providers/AppServiceProvider.php` — sem registro, não vale.

### Tratamento de erros (convenção da casa)

- **`try/catch` explícito em cada método** de controller que pode falhar. Erros inesperados fluem por `$this->tratarErro($e, 'Contexto legível')` (trait `App\Traits\TratamentoErros`), que loga, distingue `NegocioException` e responde com `redirect()->back()->withInput()->with('erro', ...)` (web) ou JSON 500 (AJAX).
- **Guard clauses de pré-requisito de negócio** (ex.: "caixa precisa estar aberto") retornam cedo com `redirect()->back()->withInput()->with('erro', ...)` — não são exceções.
- **Endpoints JSON** (ex.: calendário da Agenda) podem capturar `ValidationException`/`AuthorizationException` explicitamente para devolver status 422/403 com mensagem própria.
- **Contexto de empresa de criação (multi-empresa)**: operações de escrita transacional envolvem a chamada em `$this->comEmpresaDeCriacao($empresaId, fn () => ...)` (trait `App\Traits\DefineEmpresaDeCriacao`), que fixa e limpa a chave de sessão `empresa_criacao_atual`. Não manipule essa chave manualmente no controller.

### Idioma

Tudo em português: tabelas, campos, rotas (`->name(...)`), permissões, mensagens e nomes de método de domínio.

## Reportar issues

Encontrou um bug ou tem uma ideia? Abra uma [Issue](../../issues) descrevendo o cenário, o comportamento esperado e o observado. Screenshots ajudam quando o problema é de UI.

Boas contribuições — e divirta-se mexendo no código.
