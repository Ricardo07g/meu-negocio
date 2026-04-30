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

## Branches

Fluxo simples: `main` é a base. Para mudanças, abra uma feature branch nomeada como `feat/descricao-curta` ou `fix/descricao-curta` e abra um Pull Request contra `main`.

## Como rodar testes e lint

```bash
docker compose exec app composer test
docker compose exec app vendor/bin/pint --test
```

Ambos precisam passar antes do PR. Se você tocou em arquivos PHP, rode também `docker compose exec app vendor/bin/pint` (sem `--test`) para auto-corrigir formatação.

## Reportar issues

Encontrou um bug ou tem uma ideia? Abra uma [Issue](../../issues) descrevendo o cenário, o comportamento esperado e o observado. Screenshots ajudam quando o problema é de UI.

Boas contribuições — e divirta-se mexendo no código.
