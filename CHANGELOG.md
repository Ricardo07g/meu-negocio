# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui.
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [Não lançado]

### Adicionado
- Módulo **Assinatura/Faturamento**: tela "Minha Assinatura" (plano, uso × limites, fatura do mês,
  histórico) e troca de plano self-service (Admin) com validação de limites e fatura pró-rata
  (`TransicionarPlanoAction`). Ver [ADR-0007](docs/ADR/0007-assinatura-faturamento.md).
- Perfis de acesso de demonstração no `DesenvolvimentoSeeder` (Recepção, Profissional, Financeiro)
  com 1 usuário cada, para testar os níveis de permissão.
- Camada de automação de desenvolvimento (Claude Code): hooks de qualidade, subagents, skills e
  slash commands, empacotados como plugin em `devkit/`. Ver [docs/AUTOMACAO.md](docs/AUTOMACAO.md).
- Análise estática **PHPStan/Larastan** (nível 5) e relatório de cobertura no CI.
- `@property` docblocks em todos os models (baseline PHPStan 317 → 43).
- Screenshots reais da UI no README.

### Corrigido
- Resíduos do refactor `pacote → etapas` que quebravam telas em runtime (escapavam de testes/PHPStan):
  `vendaPacote` → `vendaEtapas` em Contas a Receber/recibo/views; `isPacote()` → `isEtapas()` e a
  coluna `vendas_etapas.data` (NOT NULL) na criação de venda em etapas; `venda_pacote_id` no seeder.
- Agenda: endpoints JSON retornam **403** (não 500) ao negar permissão.

### Alterado
- Suíte de testes ampliada de 26 → **137** testes Feature (cobertura dos 7 módulos antes sem teste +
  caminhos HTTP de risco: store/recibo/show).
- JS inline da tela de criar venda (~730 linhas) extraído para módulo Vite (`resources/js/venda-create.js`).
- Badge de status dos cards financeiros extraído para o componente `<x-badge-status>`.

## [1.0.0] - 2026-06-05

Primeira versão do MVP de portfólio.

### Adicionado
- SaaS **multi-tenant single-DB** (isolamento por `rede_id`/`empresa_id` via global scopes) com
  suporte **multi-empresa** (uma rede, N empresas; contexto por sessão/URL).
- Módulos: Auth (com reset de senha), Tenant (Rede/Empresa/Plano), Usuário, PerfilAcesso (RBAC),
  Cliente, Serviço, Produto, Agenda (Toast UI Calendar), Venda (único/etapas/produto, carrinho),
  Pagamento e Despesa (modelo Título + Parcela + Baixa), Caixa diário (com retroativo/estorno),
  Estoque e Dashboard.
- Arquitetura modular (`app/Modules/`), Controllers thin + Service/Action, Requests/DTOs unificados,
  Policies de autorização, auditoria via Spatie Activitylog.
- Setup Docker Compose, CI (GitHub Actions: testes + Pint), documentação (CLAUDE.md, ADRs, `.ai/`).
