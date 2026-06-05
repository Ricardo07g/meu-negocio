---
name: padroes-projeto
description: "Blueprints e convencoes de codigo do projeto Meu Negocio (Laravel 13 multi-tenant). Consulte SEMPRE que for escrever ou revisar codigo Laravel neste repo — criar/editar Controller, Service, Action, Model, DTO, Request, Policy, Migration, View ou factory — para garantir que o resultado segue o padrao real dos modulos existentes em vez de um estilo generico."
---

# Padroes de codigo — Meu Negocio

Este projeto tem convencoes fortes e consistentes. Antes de gerar codigo, **abra o arquivo-exemplo
canonico correspondente e copie o estilo real** (nomes, namespaces, secoes, ordem). Isso evita
divergencia e mantem a base coerente. O modulo `app/Modules/Produto/` e a referencia mais completa.

## Regras-chave (o porque importa)

- **Formatacao versionada (`pint.json`)**: rode `vendor/bin/pint`, nunca formate no olho. Preset
  `laravel` + `declare(strict_types=1)` em todo arquivo de classe (Blade e skeleton ficam de fora),
  imports do mesmo namespace agrupados com chaves (`use App\X\{A, B};`), ordenados e sem nao-usados.
  Cuidado: como imports nao usados sao removidos, **adicione import e uso na mesma alteracao**.
- **Controller fino**: so request/response + `$this->authorize(...)`, delega a Service/Action. Manter
  o controller magro deixa a regra de negocio testavel sem HTTP. Conversao de enums/datas e montagem
  de DTO podem virar metodos privados (`montarDados`, `processarVenda`) para encurtar o metodo de rota.
- **Tratamento de erros**: `try/catch` explicito por metodo. Erro inesperado flui por
  `$this->tratarErro($e, 'Contexto')` (trait `TratamentoErros`); pre-requisitos de negocio retornam
  cedo com `redirect()->back()->withInput()->with('erro', ...)`. Em escrita transacional multi-empresa,
  envolva em `$this->comEmpresaDeCriacao($empresaId, fn () => ...)` (trait `DefineEmpresaDeCriacao`) —
  nunca mexa na chave de sessao `empresa_criacao_atual` na mao. Transacao/rollback so nas Services
  via `DB::transaction(fn)`; nunca `DB::` no controller.
- **Service x Action**: Service concentra regra de negocio do modulo; Action isola uma operacao
  complexa de escrita (ex.: `VenderEtapasAction`). Use Action quando a operacao tem varios passos
  transacionais reaproveitaveis.
- **Request unificado** `SalvarXxxRequest`: um Request para criar e editar, distinguindo por
  `isMethod('post')`; quando a mesma Request serve rotas com permissoes diferentes, decida pela
  permissao com `routeIs(...)` no `authorize()`. Evita duplicar validacao.
- **DTO unificado** `XxxData` (spatie/laravel-data): um DTO para criar e atualizar. Transporta dados
  validados do controller ao service sem arrays soltos.
- **Model** estende `App\Models\BaseModel` (traz `RedeTrait` → escopo global `rede_id`). Use
  `EmpresaTrait` em dados **transacionais** (isolados por empresa). Organize o corpo em secoes ASCII
  art: RELATIONS, ACESSORS, MUTATORS, SCOPES, METHODS. Casts via metodo `casts(): array`.
- **Catalogo x Transacional**: catalogo (Cliente, Servico, Produto, categorias) e rede-level (sem
  `empresa_id`, compartilhado entre empresas da rede); transacional (Agendamento, Venda, Pagamento,
  Despesa, Caixa, Estoque) tem `empresa_id`.
- **Policy**: toda entidade sensivel tem `XxxPolicy` **registrada** em
  `app/Providers/AppServiceProvider.php` (`$policies`). Sem registro, a Policy nao vale.
- **Views**: `_form.blade.php` partial compartilhado (create/edit) com
  `@php $entidade = $entidade ?? null; @endphp`; botoes via `<x-form-botoes>`; busca de entidades por
  AJAX (`initAjaxSearch()`), nunca `<select>` carregando tudo. Padroes visuais sempre do Duralux Admin.
- **Migration**: sempre com `down()` reversivel; FKs conforme docs/ADR (cascade/null/restrict).
- **Idioma**: tudo em portugues (tabelas, campos, rotas `->name(...)`, permissoes).
- **Execucao**: PHP/Composer rodam no **Docker** — use `docker exec meu-negocio-app <cmd>`. Nunca
  assuma `php`/`composer` no host.
- **Commits**: convencao `tipo(modulo): mensagem` (`feat`, `fix`, `refactor`, `docs`, `chore`, `test`).

## Onde estao os blueprints (arquivos reais a copiar)

A tabela completa de "para escrever X, copie o arquivo Y" esta em
[references/blueprints.md](references/blueprints.md). Abra-o quando precisar do formato exato de um
artefato. Para testes/factories, veja a skill `gerar-teste-model`; para montar um modulo inteiro, a
skill `scaffold-modulo`.
