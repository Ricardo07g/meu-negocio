# Meu Negocio - Contexto do Projeto

SaaS multi-tenant para pequenos negocios (clinicas, saloes, massoterapia, autonomos).
Projeto de portfolio, preparado para open source.

## Stack

- PHP ^8.3, Laravel ^13.0
- MySQL 8.0, Redis
- Docker Compose (app, nginx:8080, mysql:3306, redis:6379)
- Vite + Tailwind CSS 4 + @toast-ui/calendar ^2.1.3
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
Caixa usa BaseModel + EmpresaTrait (isolamento por empresa alem da rede).

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
- **Agenda** — CRUD + confirmar/finalizar/cancelar, Toast UI Calendar
- **Pagamento** — Titulo+Parcelas, baixa parcial por parcela, renegociacao, cancelamento, contas a receber, recibo
- **Despesa** — Titulo+Parcelas, categorias, baixa parcial por parcela, recibo
- **Estoque** — Movimentos entrada/saida/ajuste
- **Produto** — CRUD + CategoriaProduto (descricao + ativo) + busca AJAX
- **Venda** — VendaPacote + VendaProduto (carrinho multi-item) + estorno automatico
- **Caixa** — Navegacao por dia, abrir/fechar/reabrir, sangria/reforco, retroativo
- **Dashboard** — Cards reais (agendamentos, clientes, receita, contas a receber, caixa)

### Parciais
- **Auth** — Login/Registrar
- **Papel** — Controller + Policy + Views

---

## Banco de Dados

### Tabelas
planos, redes, empresas, usuarios, clientes, servicos, agendamentos, vendas_pacote, vendas_produto, venda_produto_itens, **pagamentos, parcelas_pagamento, baixas_pagamento**, **despesas, parcelas_despesa, baixas_despesa, categorias_despesa**, produtos, categorias_produto, movimentos_estoque, caixas, movimentos_caixa

---

## Fluxos de Negocio

### Modelo financeiro: Titulo + Parcela
- **Titulo** = `Pagamento` (a receber) ou `Despesa` (a pagar). Contem `condicao_pagamento`, `forma_recebimento_prazo`, valor bruto/liquido, referencia ao originador (venda, despesa avulsa).
- **Parcela** = `ParcelaPagamento` / `ParcelaDespesa`. Tem `numero`, `data_vencimento`, `valor`, `valor_pago`, `status` (Pendente/Pago/ParcialmentePago/Cancelado), `forma_pagamento` (preenchida na baixa).
- **Baixa** = `BaixaPagamento` / `BaixaDespesa`. Vincula parcela + caixa + valor + multa/juros/desconto. Uma parcela pode ter N baixas.
- Geracao de parcelas: `App\Support\Parcelamento\CalculadoraParcelas`.

### Enums do modelo
- `CondicaoPagamento`: `a_vista`, `a_prazo`, `boleto`, `pix_parcelado`
- `FormaRecebimentoPrazo`: canais esperados de recebimento do titulo a prazo
- `StatusParcela`: `Pendente`, `Pago`, `ParcialmentePago`, `Cancelado`
- `FormaPagamento`: pix, dinheiro, cartao etc. (na parcela/baixa, NAO no titulo)

### Venda → Pagamento → Caixa
- A vista → `CriarPagamentoComParcelasAction` cria Pagamento + 1 parcela e baixa automaticamente via `CaixaService::darBaixaParcelaPagamento` (exige caixa aberto, pre-validado no controller antes da transacao)
- A prazo → cria Pagamento + N parcelas status Pendente → aparecem em Contas a Receber → baixa por parcela (forma real na baixa, exige caixa aberto)
- `forma_pagamento` **fica na parcela/baixa**, nao no titulo. O que indica fiado agora e `condicao_pagamento = a_prazo`.

### Estorno ao Cancelar
- `CaixaService::estornarPagamento`: marca parcelas Pendente->Cancelado, cria MovimentoCaixa(saida) com `valorPago()`, seta Pagamento.status=Estornado. Estoque devolvido e agendamentos cancelados pelo VendaService.

### Caixa Diario
- Navegacao prev/next por dia (`?data=YYYY-MM-DD`), 1 caixa por empresa/dia, permite retroativo. Reabertura via `ReabrirCaixaData`/`ReabrirCaixaRequest`.

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
