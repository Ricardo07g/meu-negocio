---
paths:
  - "app/Modules/Cliente/**"
---

# Modulo: Cliente

CRUD de clientes (dados pessoais + endereco). Catalogo **rede-level** (sem `empresa_id`): clientes
sao compartilhados entre as empresas da rede. Isolamento via `rede_id` (BaseModel/RedeTrait).

## Entidades & status
- **`Cliente`** (tabela `clientes`) — BaseModel + `SoftDeletes`. Sem enum de status. Campos pessoais:
  `nome` (obrigatorio), `telefone`, `telefone_whatsapp` (bool), `email`, `data_nascimento`, `cpf`,
  `sexo` (`M`/`F`/`outro`), `observacoes`. Endereco: `cep`, `estado` (2 chars), `cidade`, `bairro`,
  `logradouro`, `numero`, `complemento`. Casts: `data_nascimento` => `date`, `telefone_whatsapp`
  => bool. Relacoes hasMany: `agendamentos()`, `vendasEtapas()`, `vendasProduto()`, `pagamentos()`
  (todas por `cliente_id`). Nota: `vendasEtapas`/`vendasProduto` — modelo de venda atual e
  VendaEtapas + VendaProduto (NAO existe mais "vendaPacote").

## Camadas-chave
- `ClienteController` — CRUD resource completo + `buscar()` (AJAX). `show` faz eager load de
  `vendasEtapas.servico/atendente`, `agendamentos.servico/atendente`,
  `pagamentos.agendamento.servico`.
- `ClienteService` — `listar()` (filtros ricos, abaixo), `buscar()`, `criar()`, `atualizar()`,
  `excluir()` (soft delete). Delega criar/atualizar para as Actions.
- `CriarClienteAction` / `AtualizarClienteAction` — `executar(...)`. Convertem `data_nascimento` de
  `d/m/Y` (string do form) para Carbon; `telefone_whatsapp` default false.
- `ClienteData` — DTO unificado (Spatie); `data_nascimento` e `?string` (formato d/m/Y, convertido
  na Action — NAO e Carbon no DTO).
- `SalvarClienteRequest` — unificado (`isMethod('post')`). `data_nascimento` => `date_format:d/m/Y`,
  `cpf` => `size:14`, `cep` => `size:9`, `estado` => `size:2`, `sexo` => `in:M,F,outro`.
- `ClientePolicy` — viewAny/view => `cliente.ver`; create => `cliente.criar`; update =>
  `cliente.editar`; delete => `cliente.excluir` (view/update/delete checam `rede_id`).

## Regras de negocio / gotchas
- Permissoes (PermissaoSeeder): `cliente.ver`, `cliente.criar`, `cliente.editar`, `cliente.excluir`.
- `buscar()` (AJAX, endpoint `GET clientes/buscar?q=`): `q` >= 2 chars; busca em `nome` e `telefone`;
  retorna `id, nome, telefone`.
- Filtros do `listar()` (alem de `q` em nome/telefone/email/cpf/cidade):
  - `situacao_financeira`: `em_dia` / `pendente` (pendente nao vencido) / `vencido` (parcela
    pendente com vencimento < hoje) — via relacao `pagamentos` com `status` string `pendente`.
  - `atividade`: `novo` (criado <= 30d), `ativo` (agendamento/venda nos ult. 30d),
    `sumido_30|60|90|180` (sem atividade no periodo).
  - `aniversariantes` (mes atual), `com_whatsapp`.
- Clientes padrao sao semeados ao registrar a rede.

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — catalogo rede-level x transacional; camadas de auth.
- `.claude/rules/modelo-financeiro.md` — Pagamento/parcelas referenciadas nos filtros financeiros.
- skill `padroes-projeto`.
