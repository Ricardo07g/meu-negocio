# Meu Negocio - Contexto do Projeto

SaaS multi-tenant para pequenos negocios (clinicas, saloes, massoterapia, autonomos).
Projeto de portfolio, preparado para open source.

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

---

## Interface / UI

### Template de referencia
Sempre buscar padroes de interface no Duralux Admin:
`/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`

Componentes: cards, tables, badges, buttons, forms Bootstrap 5. Icones: Feather (`feather-*`). Modais: SweetAlert2.

### Padroes de UI consolidados
- **Formularios CRUD**: `_form.blade.php` partial compartilhado (create/edit), recebe `$entidade` (null no create)
- **Botoes de formulario**: `<x-form-botoes>` component (Voltar/Salvar, min-width 300px)
- **Botao Voltar em show**: `btn btn-light px-5 py-2` com `min-width: 300px`
- **Busca de entidades**: AJAX com `initAjaxSearch()` (funcao global no layout), nunca carregar tudo em select
- **Tabelas**: `table table-hover` ou `table table-striped table-hover`
- **Badges**: bg-success (ativo/pago), bg-warning (pendente), bg-danger (cancelado), bg-secondary (estornado)
- **Modais SweetAlert**: inputs com `width:100%;max-width:100%;box-sizing:border-box;`, textareas `rows="3"`, cor `#3454d1`
- **Models**: secoes ASCII art (RELATIONS, ACESSORS, MUTATORS, SCOPES, METHODS)

### AJAX Search (global no layout)
Endpoints: `GET clientes/buscar?q=`, `GET produtos/buscar?q=`, `GET servicos/buscar?q=`

---

## Arquitetura

### Multi-tenant
Single DB + tenant_id. Traits: `RedeTrait` (rede_id), `EmpresaTrait` (empresa_id, Admin ve tudo).

### BaseModel
`App\Models\BaseModel` extends Model + usa `RedeTrait`. Todos models tenant-aware estendem BaseModel.
Excecoes: Plano, Rede, MovimentoCaixa (Model direto). Usuario (Authenticatable + traits direto).

### Estrutura Modular
`app/Modules/{NomeModulo}/` com Controllers, Services, Actions, DTOs, Requests, Policies, Models, Views, Migrations.

### Padroes de Codigo
- Controller: request/response apenas, chama service
- Service: regra de negocio
- **Requests unificados**: `SalvarXxxRequest` (isMethod('post') para criar/editar)
- **DTOs unificados**: `XxxData` (um para criar e atualizar)
- **Views com partial**: `_form.blade.php` + `@php $entidade = $entidade ?? null; @endphp`

---

## Modulos — Estado Atual

### Completos
- **Tenant** — Rede, Empresa, Plano
- **Usuario** — CRUD completo
- **Cliente** — CRUD + Actions + busca AJAX
- **Servico** — CRUD, tipos avulso/pacote + busca AJAX
- **Agenda** — CRUD + confirmar/finalizar/cancelar, FullCalendar
- **Pagamento** — CRUD + baixa parcial + contas a receber + filtros status
- **Despesa** — CRUD completo
- **Estoque** — Movimentos entrada/saida/ajuste
- **Produto** — CRUD + CategoriaProduto (descricao + ativo) + busca AJAX
- **Venda** — VendaPacote + VendaProduto (carrinho multi-item) + estorno automatico
- **Caixa** — Navegacao por dia, abrir/fechar, sangria/reforco, retroativo
- **Dashboard** — Cards reais (agendamentos, clientes, receita, contas a receber, caixa)

### Parciais
- **Auth** — Login/Registrar
- **Papel** — Controller + Policy + Views

---

## Banco de Dados

### Tabelas
planos, redes, empresas, usuarios, clientes, servicos, agendamentos, vendas_pacote, vendas_produto, venda_produto_itens, pagamentos, baixas_pagamento, despesas, produtos, categorias_produto, movimentos_estoque, caixas, movimentos_caixa

---

## Fluxos de Negocio

### Venda → Pagamento → Caixa
- Venda paga + caixa aberto → MovimentoCaixa entrada
- Venda pendente (fiado) → Contas a Receber → Baixa parcial/total (exige caixa aberto)

### Estorno ao Cancelar
- Pagamento estornado + estoque devolvido + saida no caixa + agendamentos cancelados

### Caixa Diario
- Navegacao prev/next por dia, 1 caixa por empresa/dia, permite retroativo

---

## Traits
| Trait | Uso |
|---|---|
| RedeTrait | Global scope rede_id (via BaseModel) |
| EmpresaTrait | Global scope empresa_id (Admin ve tudo) |
| RegistraAtividade | Spatie ActivityLog |
| TratamentoErros | Error handling controllers |

---

## Seeds (ao registrar)
Categorias, produtos, servicos, clientes padrao criados automaticamente ao registrar nova rede.

---

## Regras para IA

1. **Sempre buscar padroes visuais** no Duralux Admin antes de criar UI
2. **Sempre perguntar antes** de criar algo grande
3. **Nunca pular etapas**
4. **Validar sempre**: auth, tenant, permissao, plano
5. **Nunca permitir** acesso cruzado entre redes/empresas
6. **Requests unificados**: SalvarXxxRequest
7. **DTOs unificados**: XxxData
8. **Views com partial**: _form.blade.php + $entidade
9. **Busca AJAX**: nunca carregar todos em select HTML
10. **Models**: BaseModel + secoes ASCII art
