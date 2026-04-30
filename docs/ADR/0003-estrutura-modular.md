# ADR-0003 — Estrutura modular em `app/Modules/`

## Status

Aceito — abril/2026.

## Contexto

A estrutura padrão do Laravel concentra código por **camada**: `app/Http/Controllers`, `app/Models`, `app/Services` (quando o time adota), `database/migrations`, `resources/views`. Para projetos pequenos é direto. Para projetos com 14+ domínios (Tenant, Auth, Usuario, Cliente, Servico, Agenda, Venda, Pagamento, Despesa, Caixa, Estoque, Produto, PerfilAcesso, Dashboard), essa organização espalha o domínio por todo o projeto:

- O Controller de Venda fica longe do Service de Venda, que fica longe da migration de Venda, que fica longe da view de Venda.
- Renomear ou remover um módulo exige caçar arquivos em 5+ pastas.
- O onboarding do dev novo precisa explicar "onde fica X" para cada nova entidade.

Como o projeto é peça de portfólio com ambição de demonstrar Clean Architecture e separação por domínio, faz sentido investir na reorganização.

## Decisão

Adotamos **organização modular por domínio**, em `app/Modules/{Modulo}/`:

```
app/Modules/Venda/
├── Controllers/
├── Services/
├── Actions/
├── DTOs/
├── Requests/
├── Policies/
├── Models/
├── Views/        # namespace de view: venda::nome
└── Migrations/   # carregadas pelo ModuleServiceProvider
```

Cada módulo é **auto-contido**: tudo que pertence a Venda mora em `app/Modules/Venda/`. Um `ModuleServiceProvider` global descobre os módulos, registra views (`venda::nome`), policies e migrations.

Módulos transversais (Auth, Tenant) seguem o mesmo padrão.

## Consequências

### Positivas
- **Coesão alta por domínio**: navegar para entender Venda é literalmente "abrir uma pasta".
- **Acoplamento explícito**: dependências cross-módulo aparecem nos `use` statements e ficam óbvias na revisão.
- **Onboarding mais rápido**: a estrutura repete entre módulos — quem entendeu Cliente entende Produto e Servico.
- **Suporta Domain-Driven Design** se o projeto crescer: módulos podem virar bounded contexts no futuro.
- **Migrations contextualizadas**: `app/Modules/Venda/Migrations/` deixa claro que aquela migration pertence ao domínio (em vez de afogar em `database/migrations/`).

### Negativas
- **Foge do default Laravel**: dev acostumado com a estrutura padrão precisa de 5 minutos de orientação.
- **Discovery não é gratuito**: o `ModuleServiceProvider` é um ponto extra de complexidade que precisa ser mantido (load de migrations, registro de views, namespaces).
- **Risco de duplicação**: traits/utilitários que cobrem múltiplos módulos ficam em `app/Traits` e `app/Support`, o que cria duas hierarquias para o leitor entender (módulos vs raiz).

### Neutras
- Módulos transversais ainda existem (`Auth`, `Tenant`) e seguem o mesmo padrão — não há exceção estrutural.
- A estrutura pode ser convertida em pacote Composer no futuro se algum módulo justificar reuso entre projetos. Hoje, não justifica.
