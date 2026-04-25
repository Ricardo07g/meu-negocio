# Implementado

Estado em: 2026-04-14

## Infraestrutura

- [x] Docker Compose (app, nginx, mysql, redis)
- [x] Laravel ^13.0 com PHP ^8.3
- [x] Spatie Permission, Data, ActivityLog instalados
- [x] Vite + Tailwind + Toast UI Calendar configurados
- [x] Template Duralux Admin integrado no layout
- [x] ModuleServiceProvider (auto-load de modulos)

## Multi-tenant

- [x] Traits PertenceARede e PertenceAEmpresa
- [x] Middleware VerificarRede, VerificarEmpresa, VerificarPlano
- [x] Global scopes automaticos
- [x] Auto-assign de rede_id/empresa_id

## Modulos completos (todas camadas)

| Modulo | Model | Controller | Service | Action | DTO | Request | Policy | Views | Migrations |
|--------|-------|-----------|---------|--------|-----|---------|--------|-------|-----------|
| Tenant | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Usuario | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Cliente | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Servico | sim | sim | sim | — | sim | sim | sim | sim | sim |
| Agenda | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Pagamento | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Despesa | sim | sim | sim | — | sim | sim | sim | sim | sim |
| Estoque | sim | sim | sim | — | sim | sim | sim | sim | sim |
| Produto | sim | sim | sim | — | sim | sim | sim | sim | sim |
| Venda | sim | sim | sim | sim | sim | sim | sim | sim | sim |
| Caixa | sim | sim | sim | — | sim | sim | sim | sim | sim |

## Modulos parciais

| Modulo | O que tem | O que falta |
|--------|-----------|-------------|
| Auth | Controllers, Requests, Views | Service, DTO (usa RedeService) |
| Dashboard | Controller, View | Conteudo real (dados, graficos) |
| Papel | Controller, Policy, Views | Service, DTO (usa Spatie direto) |

## Enums

- [x] StatusAgendamento, FormaPagamento, StatusPagamento
- [x] TipoServico, StatusVendaPacote, TipoMovimentoEstoque
- [x] StatusRede, StatusCaixa, TipoMovimentoCaixa, PapelEnum

## Funcionalidades implementadas

- [x] Registro com criacao automatica de rede + empresa + usuario admin
- [x] Login/logout com verificacao de usuario ativo
- [x] CRUD completo de clientes (com endereco e dados pessoais)
- [x] CRUD de servicos (avulso e pacote)
- [x] CRUD de produtos com categorias
- [x] Calendario de agendamentos (Toast UI Calendar)
- [x] Criacao de agendamentos com verificacao de conflito
- [x] Ciclo de vida: agendar → confirmar → finalizar/cancelar
- [x] Vendas: avulso, pacote (multiplas sessoes), produto
- [x] Pagamentos com pagamento parcial (baixas)
- [x] Caixa: abertura, fechamento, sangria, reforco
- [x] Movimentacao de estoque (entrada, saida, ajuste)
- [x] Gestao de papeis e permissoes
- [x] Validacao de plano (limites e features)
- [x] Activity log em agendamentos e pagamentos
- [x] SoftDeletes nas entidades principais
- [x] Input masks (telefone, CPF, CEP, data)
- [x] Auto-preenchimento de endereco via ViaCEP
- [x] SweetAlert2 para confirmacoes
- [x] Menu lateral com permissoes (@can)
