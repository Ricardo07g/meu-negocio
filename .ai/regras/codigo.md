# Regras de Codigo

Convencoes de desenvolvimento do projeto.

## Idioma

Tudo em portugues: tabelas, models, controllers, campos, permissoes, rotas, views.

## Estrutura

- Cada modulo em `app/Modules/{NomeModulo}/`
- Camadas obrigatorias: Model, Controller, Service, DTO, Request, Policy, Views, Migrations
- Actions para operacoes especificas (opcional, quando logica justifica)

## Controller

- **Nunca** colocar regra de negocio
- Apenas: validar request, chamar service, retornar response
- Usar trait `TratamentoErros` para error handling
- Usar `$this->authorize()` para autorizacao via Policy
- DTOs criados com `::from($request->validated())`

## Service

- Contem regra de negocio e fluxo
- Pode usar Actions, DTOs, Models
- Pode chamar outros Services quando necessario

## Action

- Faz uma coisa so (single responsibility)
- Usado dentro do Service
- Exemplos: CriarAgendamentoAction, CancelarAgendamentoAction

## Model

- Sem regra complexa
- Definir: fillable, casts, relacoes
- Usar traits de tenant (PertenceARede, PertenceAEmpresa)
- SoftDeletes quando aplicavel
- Accessors/mutators apenas para transformacoes simples

## DTO

- Usar spatie/laravel-data
- Para entrada e saida de dados
- Nunca passar array solto entre camadas

## Request

- Cada endpoint deve ter Request proprio
- Nunca validar no controller
- Regras de validacao no Request

## Policy

- Todos models devem ter Policy
- Nunca validar permissao no controller diretamente (usar authorize)
- Permissoes no formato `{recurso}.{acao}`

## Views

- Usar `@extends('layouts.app')`
- Referenciadas como `modulo::view`
- Seguir componentes do template Duralux
- Flash messages: chave `sucesso` ou `erro`

## Rotas

- Verbos em portugues: `/novo` (create), `/editar` (edit)
- Registradas no `web.php`
- Middleware na ordem: auth > verificar.rede > verificar.empresa > verificar.plano

## Nomenclatura

| Tipo | Exemplo |
|------|---------|
| Tabela | `clientes`, `vendas_pacote`, `movimentos_estoque` |
| Model | `Cliente`, `VendaPacote`, `MovimentoEstoque` |
| Controller | `ClienteController`, `VendaController` |
| Service | `ClienteService`, `VendaService` |
| Action | `CriarClienteAction`, `CancelarAgendamentoAction` |
| DTO | `CriarClienteData`, `AtualizarClienteData` |
| Request | `CriarClienteRequest`, `AtualizarClienteRequest` |
| Policy | `ClientePolicy`, `AgendamentoPolicy` |
| Enum | `StatusAgendamento`, `FormaPagamento` |
| Permissao | `cliente.ver`, `agendamento.cancelar` |
