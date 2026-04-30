# Meu Negócio

> SaaS multi-tenant para a gestão de pequenos negócios — clínicas, salões, massoterapia e profissionais autônomos. Agenda, vendas, financeiro, caixa diário e estoque em um único produto, isolado por rede e por empresa.

[![CI](https://github.com/Ricardo07g/meu-negocio/actions/workflows/ci.yml/badge.svg)](https://github.com/Ricardo07g/meu-negocio/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-%5E13.0-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-Alpine-DC382D?logo=redis&logoColor=white)](https://redis.io/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Projeto de **portfólio**: foco em demonstrar arquitetura modular, multi-tenancy single-DB, padrão Título + Parcela + Baixa, Service/Action layer, DTOs unificados (Spatie Laravel Data) e Policies de autorização. Não há gateway de pagamento real, observabilidade avançada ou app mobile (ver [Roadmap](#roadmap)).

---

## Sumário

- [Pitch](#pitch)
- [Screenshots](#screenshots)
- [Stack & Arquitetura](#stack--arquitetura)
- [Setup local com Docker](#setup-local-com-docker)
- [Credenciais demo](#credenciais-demo)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Roadmap](#roadmap)
- [Licença](#licença)

---

## Pitch

Donas de pequenos salões, clínicas e profissionais autônomos costumam viver com planilha + WhatsApp + bloco de papel. **Meu Negócio** consolida o operacional do dia-a-dia em uma só ferramenta:

- **Agenda** com bloqueio de conflito de horário e múltiplos atendentes (calendário Toast UI).
- **Vendas** de serviços avulsos, pacotes (várias sessões) e produtos físicos, com carrinho multi-item.
- **Financeiro** modelado como Título + Parcela + Baixa: aceita pagamento à vista, à prazo, parcial e renegociação.
- **Caixa diário** com abertura/fechamento, sangria, reforço e estorno automático ao cancelar venda.
- **Estoque** com movimentos de entrada/saída/ajuste vinculados a vendas de produto.
- **Multi-tenant single-DB** com isolamento por `rede_id` e `empresa_id` via Eloquent global scopes.

Stack moderna (PHP 8.3 + Laravel 13), código em português, padrões consistentes módulo a módulo.

---

## Screenshots

Imagens capturadas após rodar o `DesenvolvimentoSeeder` (rede demo com 500 clientes, 600 agendamentos, 100 vendas e 45 dias de caixa retroativo).

> Os screenshots ficam em [`docs/screenshots/`](docs/screenshots/). Para capturá-los localmente, suba o ambiente com Docker, execute o seed de desenvolvimento e siga as instruções em [`docs/screenshots/README.md`](docs/screenshots/README.md).

| Tela | Descrição |
|------|-----------|
| Dashboard | Cards de agendamentos do dia, clientes, receita, contas a receber e situação do caixa |
| Agenda | Calendário semanal com cores por atendente e detalhamento de cada slot |
| Venda em andamento | Carrinho com produtos, total, cliente e condição de pagamento (à vista / à prazo) |
| Contas a Receber | Lista de parcelas pendentes com baixa parcial e renegociação |
| Caixa diário | Movimentos do dia, sangrias, reforços e baixas |

---

## Stack & Arquitetura

### Backend
- **PHP** ^8.3
- **Laravel** ^13.0
- **MySQL** 8.0 + **Redis** (cache/session/queue)
- **Spatie Laravel Permission** ^7.2 (RBAC)
- **Spatie Laravel Data** ^4.20 (DTOs imutáveis)
- **Spatie Laravel Activitylog** ^4.12 (auditoria)
- **barryvdh/laravel-dompdf** (recibos PDF)

### Frontend
- **Vite** ^8.0 + **Tailwind CSS** ^4.0
- **@toast-ui/calendar** ^2.1.3 (agenda)
- **Bootstrap 5** (template Duralux Admin)
- **SweetAlert2** (modais de confirmação)

### Infra de dev
- **Docker Compose** (app PHP-FPM + Nginx 8080 + MySQL 3306 + Redis 6379)

### Padrões aplicados

- **Estrutura modular**: cada domínio em `app/Modules/{Modulo}/` com Controllers / Services / Actions / DTOs / Requests / Policies / Models / Views / Migrations.
- **Multi-tenant single-DB**: traits `RedeTrait` (rede) e `EmpresaTrait` (empresa, com bypass para Admin). Aplicadas via `BaseModel` para consistência.
- **Controllers thin**: pegam request e devolvem response. Toda a regra fica em Service ou Action.
- **Requests unificados**: `SalvarXxxRequest` com `isMethod('post')` para diferenciar criação e atualização.
- **DTOs unificados**: um `XxxData` (Spatie Data) usado tanto para criar quanto para atualizar.
- **Views com partial**: `_form.blade.php` recebe `$entidade` e é incluído por `create.blade.php` e `edit.blade.php`.
- **Modelo financeiro**: Título (`Pagamento` / `Despesa`) + Parcela + Baixa. `condicao_pagamento` (à vista / à prazo) decide o fluxo, `forma_pagamento` fica na parcela/baixa, não no título.
- **Permissões dinâmicas**: catálogo de permissions fixo no código (`recurso.acao`), Roles criados pelo Admin via UI (`/perfis-acesso`). Apenas o Admin master é seedado.

### Documentação interna

A pasta [`.ai/`](.ai/README.md) contém ~30 arquivos de contexto organizados em `contexto/`, `modulos/`, `fluxos/`, `guias/`, `progresso/` e `regras/`. Foi escrita para acelerar onboarding de assistentes de IA, mas serve igualmente bem para um humano que precise entender uma decisão.

A [`CLAUDE.md`](CLAUDE.md) na raiz é o guia consolidado de convenções do projeto.

### Decisões arquiteturais (ADRs)

As decisões marcantes da arquitetura estão registradas em [`docs/ADR/`](docs/ADR/README.md) no formato MADR-light. Tópicos cobertos: multi-tenant single-DB, modelo financeiro Título+Parcela+Baixa, estrutura modular, BaseModel+traits para tenancy, caixa diário retroativo e padrões de foreign keys.

---

## Setup local com Docker

Pré-requisitos: Docker e Docker Compose v2.

```bash
# 1. Clonar
git clone https://github.com/<seu-usuario>/meu-negocio.git
cd meu-negocio

# 2. Copiar .env exemplo
cp .env.example .env

# 3. Subir os containers (app, nginx:8080, mysql:3306, redis:6379)
docker compose up -d

# 4. Instalar dependências PHP e gerar APP_KEY
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 5. Rodar migrations + seeders base (planos + permissões + Admin)
docker compose exec app php artisan migrate:fresh --seed

# 6. (Opcional) Popular com dados volumosos para demonstração
docker compose exec app php artisan db:seed --class=DesenvolvimentoSeeder

# 7. Build dos assets
docker compose exec app npm install
docker compose exec app npm run build
```

Acesse [http://localhost:8080](http://localhost:8080).

### Dia-a-dia

```bash
# Ver logs
docker compose logs -f app

# Shell no container PHP
docker compose exec app bash

# Rodar testes
docker compose exec app composer test

# Lint (PSR-12 via Laravel Pint)
docker compose exec app vendor/bin/pint --test
```

---

## Credenciais demo

Após o `DesenvolvimentoSeeder`:

| Email | Senha | Papel |
|-------|-------|-------|
| `admin@teste.com` | `password` | Admin (acesso total) |
| `atendente1@teste.com` ... `atendente5@teste.com` | `password` | Admin com `atende = true` (aparece no select da agenda) |

> Para fluxo de **reset de senha**: tela de login → "Esqueci minha senha" → digite o email → o email vai para `storage/logs/laravel.log` (driver `log`, sem necessidade de SMTP real).

---

## Estrutura de pastas

```
meu-negocio/
├── .ai/                    # Documentação interna (contexto, módulos, fluxos, guias)
├── app/
│   ├── Modules/            # 14 módulos: Tenant, Auth, Usuario, Cliente, Servico,
│   │   │                   # Agenda, Venda, Pagamento, Despesa, Caixa, Estoque,
│   │   │                   # Produto, PerfilAcesso, Dashboard
│   │   └── {Modulo}/
│   │       ├── Controllers/
│   │       ├── Services/
│   │       ├── Actions/
│   │       ├── DTOs/
│   │       ├── Requests/
│   │       ├── Policies/
│   │       ├── Models/
│   │       ├── Views/      # Namespace de view: {modulo}::nome
│   │       └── Migrations/ # Carregadas pelo ModuleServiceProvider
│   ├── Enums/              # Enums de status do domínio
│   ├── Http/Middleware/    # verificar.rede, verificar.empresa, verificar.plano
│   ├── Models/BaseModel    # Eloquent base com RedeTrait
│   ├── Providers/          # AppServiceProvider, ModuleServiceProvider
│   ├── Support/            # CalculadoraParcelas e helpers do domínio
│   └── Traits/             # RedeTrait, EmpresaTrait, RegistraAtividade, TratamentoErros
├── database/
│   ├── migrations/         # Migrations transversais (cache, jobs, soft-deletes, etc.)
│   └── seeders/
│       ├── PlanoSeeder
│       ├── PermissaoSeeder         # Catálogo de permissions + Admin master
│       └── DesenvolvimentoSeeder   # Dados volumosos para demonstração
├── docker/                 # Dockerfiles (php, nginx, mysql)
├── docker-compose.yml
├── docs/
│   ├── FECHAMENTO_PORTFOLIO.md     # Backlog técnico de fechamento de escopo
│   └── screenshots/                # Imagens da UI
├── resources/
│   ├── js/calendar.js              # Toast UI Calendar
│   └── views/layouts/              # auth.blade.php, app.blade.php (Duralux)
└── routes/web.php
```

---

## Roadmap

Itens **intencionalmente fora de escopo** deste portfólio. Cada um é grande e o objetivo aqui é demonstrar arquitetura, não construir um produto comercial completo.

| Tema | Por que ficou de fora |
|------|----------------------|
| **Módulo de Relatórios** (BI, gráficos, exportação) | Esforço alto para algo decorativo no contexto de portfólio |
| **2FA / autenticação multi-fator** | Reset de senha já cobre o gap visível em auth |
| **Verificação de email no registro** | Mesmo motivo do 2FA |
| **Gateway de pagamento** (Stripe/MercadoPago/Asaas) | Exige sandbox real e env vars de produção |
| **API REST/GraphQL** | Não há frontend mobile que justifique a superfície adicional |
| **Observability** (Sentry, NewRelic, métricas) | Overhead operacional sem retorno em portfólio |
| **i18n real** | Projeto é PT-BR by design |
| **WebSockets / atualizações em tempo real** | Complexidade alta vs. valor incremental |

Considerações de produção (auto-hospedagem do projeto): adicionar APP_KEY real, fila de jobs com supervisor, MAIL_MAILER SMTP, backup do MySQL, HTTPS na borda (Caddy/Traefik) e log centralizado.

---

## Licença

Este projeto está sob a licença MIT — ver [`LICENSE`](LICENSE).

---

Built with [Laravel](https://laravel.com), [Tailwind CSS](https://tailwindcss.com), [Toast UI Calendar](https://github.com/nhn/tui.calendar), e a stack Spatie. Template visual: [Duralux Admin](https://duralux.minible.com/) (Bootstrap 5).
