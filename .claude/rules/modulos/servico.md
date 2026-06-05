---
paths:
  - "app/Modules/Servico/**"
---

# Modulo: Servico

CRUD do catalogo de servicos da rede (rede-level, sem `empresa_id` — compartilhado entre empresas).
Dois tipos: **unico** (sessao avulsa) e **etapas** (multiplas sessoes vendidas como pacote).

## Entidades & status
- **Model `Servico`** (tabela `servicos`), estende `BaseModel`, `SoftDeletes`. So `RedeTrait`
  (NAO usa EmpresaTrait — e catalogo, compartilhado pela rede).
- Campos reais: `nome`, `duracao` (minutos), `valor` (decimal:2), `tipo`, `qtd_etapas` (int|null),
  `descricao`. **Nao existe mais `qtd_sessoes` nem `dias_semana`** (removidos/renomeados).
- Enum **`TipoServico`** (`app/Enums/TipoServico.php`): `Unico = 'unico'`, `Etapas = 'etapas'`.
  **Nao ha mais `Avulso`/`Pacote`.** Sem metodo `cor()` (so `label()`).
- Helpers no model: `isUnico(): bool`, `isEtapas(): bool`.
- Relacoes: `agendamentos()` (HasMany), `vendasEtapas()` (HasMany `VendaEtapas`, FK `servico_id`).

## Camadas-chave
- **`ServicoController`**: CRUD resource (`Route::resource('servicos', ...)`) + `buscar()` (AJAX,
  `GET servicos/buscar?q=`, min 2 chars, retorna id/nome/tipo/duracao/valor/qtd_etapas). `show()`
  pagina agendamentos e (se `isEtapas()`) as `vendasEtapas`.
- **`ServicoService`**: `listar` (filtros q/tipo/valor/duracao), `buscar`, `criar`, `atualizar`,
  `excluir`. Sem transacao (CRUD simples).
- **`ServicoData`** (DTO unificado): `tipo` default `TipoServico::Unico`, `qtd_etapas` nullable.
- **`SalvarServicoRequest`** (unificado, isMethod('post') decide criar/editar): `qtd_etapas` e
  `required_if:tipo,etapas` + `min:2`.
- **`ServicoPolicy`**: permissoes `servico.ver|criar|editar|excluir`; checa `rede_id` (sem empresa).

## Regras de negocio / gotchas
- Tipo `etapas` define apenas que a venda gera N agendamentos; a quantidade real de sessoes na venda
  vem das `datas` enviadas no form de Venda (`VendaEtapas.qtd_etapas = count(datas)`), **nao** de
  `servico.qtd_etapas` (este e so referencia/sugestao no catalogo).
- `duracao` (minutos) e usada por `CriarAgendamentoAction` / `VenderEtapasAction` para calcular `fim`.
- Migrations da pasta tem historico confuso (add_tipo adiciona `qtd_etapas`+`dias_semana`, depois
  remove, depois re-adiciona `qtd_etapas`+`descricao`); o estado final e o `$fillable` acima.

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — catalogo rede-level vs transacional.
- `.claude/rules/modulos/venda.md` e `agenda.md` — consumidores de Servico.
