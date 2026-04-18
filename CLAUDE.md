# Meu Negocio - Contexto do Projeto

SaaS multi-tenant para pequenos negocios (clinicas, saloes, massoterapia, autonomos).
Preparado para open source.

## Stack

- PHP ^8.3, Laravel ^13.0
- MySQL 8.0, Redis
- Docker Compose (app, nginx:8080, mysql:3306, redis:6379)
- Vite + Tailwind CSS 4 + FullCalendar 6
- Template: Duralux Admin 1.0.0 (`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`)

## Pacotes Obrigatorios

- `spatie/laravel-permission` ^7.2 — papeis e permissoes
- `spatie/laravel-data` ^4.20 — DTOs
- `spatie/laravel-activitylog` ^4.12 — auditoria

## Idioma

Tudo em portugues: tabelas, models, controllers, campos, permissoes, rotas.
Exemplo: `clientes`, `Cliente`, `ClienteController`, `cliente.ver`.

---

## Arquitetura

### Multi-tenant

Single database + tenant_id. Campos obrigatorios em todos os registros:
- `rede_id` — sempre
- `empresa_id` — quando dado pertence a empresa

Traits de escopo automatico:
- `PertenceARede` — global scope por `rede_id` do usuario logado, auto-assign no boot
- `PertenceAEmpresa` — global scope por `empresa_id` (Admin ve tudo da rede), auto-assign no boot

Hierarquia: `Rede > Empresa > (Usuarios, Clientes, Servicos, Agendamentos, ...)`

### Estrutura Modular

Cada modulo fica em `app/Modules/{NomeModulo}/` com subpastas por camada.
O `ModuleServiceProvider` carrega views e migrations automaticamente de cada modulo.

### Camadas por Modulo

| Camada       | Pasta            | Responsabilidade                              |
|-------------|-----------------|----------------------------------------------|
| Controller  | Controllers/     | Request/Response. Nunca regra de negocio.    |
| Service     | Services/        | Regra de negocio e fluxo.                     |
| Action      | Actions/         | Acao especifica e unitaria.                   |
| DTO         | DTOs/            | Transporte de dados (spatie/laravel-data).    |
| Request     | Requests/        | Validacao de entrada.                         |
| Policy      | Policies/        | Autorizacao de acesso.                        |
| Model       | Models/          | Eloquent. Sem regra complexa.                 |
| View        | Views/           | Blade templates.                              |
| Migration   | Migrations/      | Estrutura do banco.                           |

### Regras de Codigo

- Controller so valida request, chama service, retorna response
- Service contem regra de negocio, pode usar Actions/DTOs
- Nunca acessar DB direto no controller
- Nunca passar array solto — usar DTO
- Todos models devem ter Policy
- Repository apenas quando query complexa/reuso justificar

---

## Modulos — Estado Atual

### Completos (todas camadas implementadas)
- **Tenant** — Rede, Empresa, Plano (Models, Controllers, Services, Actions, DTOs, Requests, Policies, Views, Migrations)
- **Usuario** — CRUD completo com CriarUsuarioAction
- **Cliente** — CRUD completo com Actions de criar/atualizar
- **Servico** — CRUD completo, tipos: avulso/pacote
- **Agenda** — CRUD + acoes confirmar/finalizar/cancelar, FullCalendar integrado
- **Pagamento** — CRUD + RegistrarPagamentoAction
- **Despesa** — CRUD completo
- **Estoque** — Movimentos de entrada/saida/ajuste
- **Produto** — CRUD + CategoriaProduto
- **Venda** — VendaPacote + VendaProduto + VenderPacoteAction
- **Caixa** — Abrir/fechar, sangria/reforco, BaixaPagamento, MovimentoCaixa

### Parciais (sem todas camadas)
- **Auth** — Login/Registrar (Controllers, Requests, Views). Sem Service/DTO.
- **Dashboard** — Controller + View apenas
- **Papel** — Controller + Policy + Views (usa Spatie Role direto)

---

## Banco de Dados

### Tabelas principais
| Tabela               | Modulo    | Tenant        |
|---------------------|-----------|---------------|
| planos              | Tenant    | —             |
| redes               | Tenant    | rede_id       |
| empresas            | Tenant    | rede_id       |
| usuarios            | Usuario   | rede_id + empresa_id |
| clientes            | Cliente   | rede_id + empresa_id |
| servicos            | Servico   | rede_id + empresa_id |
| agendamentos        | Agenda    | rede_id + empresa_id |
| vendas_pacote       | Venda     | rede_id + empresa_id |
| vendas_produto      | Venda     | rede_id + empresa_id |
| pagamentos          | Pagamento | rede_id + empresa_id |
| despesas            | Despesa   | rede_id + empresa_id |
| produtos            | Produto   | rede_id + empresa_id |
| categorias_produto  | Produto   | rede_id + empresa_id |
| movimentos_estoque  | Estoque   | rede_id + empresa_id |
| caixas              | Caixa     | rede_id + empresa_id |
| movimentos_caixa    | Caixa     | rede_id + empresa_id |
| baixas_pagamento    | Caixa     | rede_id + empresa_id |

### Migrations
- Migrations de modulo ficam em `app/Modules/{Modulo}/Migrations/`
- Migrations globais (Spatie, jobs, cache) ficam em `database/migrations/`
- `ModuleServiceProvider` carrega todas automaticamente

---

## Enums

| Enum                   | Valores                                      |
|-----------------------|----------------------------------------------|
| StatusAgendamento     | Agendado, Confirmado, Cancelado, Finalizado  |
| FormaPagamento        | Pix, Dinheiro, Cartao, Fiado                 |
| StatusPagamento       | Pendente, Pago, Cancelado, Estornado         |
| TipoServico           | Avulso, Pacote                                |
| StatusVendaPacote     | Ativo, Concluido, Cancelado                   |
| TipoMovimentoEstoque  | Entrada, Saida, Ajuste                        |
| StatusRede            | Ativa, Inativa, Suspensa, Cancelada           |
| StatusCaixa           | Aberto, Fechado                               |
| TipoMovimentoCaixa   | Entrada, Saida, Sangria, Reforco              |
| PapelEnum             | Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador |

---

## Permissoes e Papeis (Spatie)

### Papeis
Dono, Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador.

### Permissoes (formato: `recurso.acao`)
- rede: ver, editar, configurar, cobranca
- empresa: ver, criar, editar, excluir
- usuario: ver, criar, editar, excluir
- cliente: ver, criar, editar, excluir
- servico: ver, criar, editar, excluir
- profissional: ver, criar, editar, excluir
- agendamento: ver, criar, editar, cancelar, excluir
- financeiro: ver, criar, editar, excluir, relatorio
- pagamento: ver, criar, editar, excluir
- despesa: ver, criar, editar, excluir
- estoque: ver, criar, editar, excluir
- produto: ver, criar, editar, excluir
- movimento_estoque: ver, criar
- plano: ver, alterar

### Planos e limites
| Plano    | Empresas | Usuarios  | Estoque | Financeiro |
|----------|----------|-----------|---------|------------|
| free     | 1        | 2         | nao     | nao        |
| basic    | 2        | 5         | sim     | basico     |
| pro      | 5        | 10        | sim     | completo   |
| business | ilimitado| ilimitado | sim     | completo   |

---

## Middleware

- `verificar.rede` — valida rede do usuario logado
- `verificar.empresa` — valida empresa do usuario
- `verificar.plano:{modulo}` — valida se plano permite acesso ao modulo (ex: `verificar.plano:financeiro`)

Ordem nas rotas: `auth > verificar.rede > verificar.empresa > verificar.plano`

---

## Rotas (web.php)

- Guest: login, registrar
- Auth + rede: dashboard
- Auth + rede + empresa: agenda, vendas, clientes, servicos, pagamentos, despesas, caixas, produtos, categorias-produto, movimentos-estoque, empresas, usuarios, papeis
- Verificacao de plano: financeiro (pagamentos, despesas, caixas), estoque (movimentos-estoque)

---

## Traits

| Trait              | Uso                                                  |
|-------------------|------------------------------------------------------|
| PertenceARede     | Global scope rede_id + auto-assign                   |
| PertenceAEmpresa  | Global scope empresa_id (Admin ve tudo) + auto-assign |
| RegistraAtividade | Spatie ActivityLog em models                          |
| TratamentoErros   | Error handling em controllers (NegocioException, etc) |

---

## Auth

- Model: `App\Modules\Usuario\Models\Usuario`
- Guard: `web` (session)
- Usuario tem: rede_id, empresa_id, papel (via Spatie)

---

## Docker (dev)

```bash
docker compose up -d        # subir ambiente
docker compose exec app bash # acessar container PHP
```

Servicos: app (PHP), nginx (:8080), mysql (:3306), redis (:6379)

---

## Comandos uteis

```bash
composer dev                 # servidor dev completo (concurrent)
php artisan migrate          # rodar migrations
php artisan db:seed          # seeders
npm run dev                  # vite dev server
npm run build                # build producao
```

---

## Regras para IA

1. **Sempre perguntar antes** de criar algo grande (tabela, modulo, layout)
2. **Sempre explicar antes** de gerar codigo
3. **Nunca pular etapas** — seguir fluxo do `instrucoes.md`
4. **Nunca criar tabela/layout** sem confirmacao
5. **Seguir INSTRUCTIONS/** — DATABASE.md, TENANT.md, PERMISSIONS.md, ARCHITECTURE.md
6. **Validar sempre**: auth, tenant (rede_id), empresa (empresa_id), permissao, plano
7. **Nunca permitir** acesso cruzado entre redes ou empresas
8. **Novo modulo deve ter**: Model, Controller, Service, DTO, Request, Policy, View, Migration
