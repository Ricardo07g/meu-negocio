---
name: scaffold-modulo
description: "Cria um novo modulo completo (ou completa partes faltantes) do Meu Negocio seguindo a arquitetura modular. Use quando o usuario pedir para criar/estruturar um modulo, recurso CRUD ou entidade nova (ex.: 'crie o modulo Comissao', 'preciso de um CRUD de Fornecedor', 'estrutura para Agendamento recorrente'), mesmo sem dizer a palavra 'modulo'."
argument-hint: "<NomeDoModulo> [descricao curta do recurso]"
---

# Scaffold de modulo — Meu Negocio

Procedimento para materializar um modulo no padrao `app/Modules/{NomeModulo}/`. O objetivo e um
esqueleto consistente com os modulos existentes — nunca um estilo proprio.

## Antes de gerar

1. **Verifique duplicidade**: `grep -ri "<nome>" app/Modules` para confirmar que o modulo/entidade
   ainda nao existe (evita recriar).
2. **Carregue os padroes**: consulte a skill `padroes-projeto` (blueprints e regras) para os formatos
   exatos de cada artefato. Use o modulo `app/Modules/Produto/` como espelho de um CRUD de catalogo.
3. **Classifique o dado**: e **catalogo** (rede-level, sem `empresa_id`) ou **transacional**
   (isolado por empresa, usa `EmpresaTrait`)? Isso define o Model e a Migration.

## Passos

1. Crie a estrutura conforme necessario:
   `Controllers/ Services/ Actions/ DTOs/ Requests/ Policies/ Models/ Views/ Migrations/`.
2. Gere os artefatos copiando o estilo canonico (ver `padroes-projeto`): Model (BaseModel + secoes
   ASCII), `SalvarXxxRequest`, `XxxData`, Service, Controller fino com `authorize()`, `XxxPolicy`,
   Migration com `down()`, Views com `_form.blade.php` partial.
3. **Registre a Policy** em `app/Providers/AppServiceProvider.php` (`$policies`).
4. **Registre a rota** em `routes/web.php` (nome em portugues, grupo adequado).
5. Marque com `// TODO:` apenas onde houver regra de negocio a definir, e liste esses pontos.
6. Rode a migration: `docker exec meu-negocio-app php artisan migrate`.
7. Gere os testes com a skill `gerar-teste-model` (ou o agente `laravel-test-writer`).
8. Formate: `docker exec meu-negocio-app vendor/bin/pint app/Modules/<NomeModulo>`.

## Quando delegar ao agente

Para um modulo grande e autonomo, delegue ao subagente **laravel-module-scaffolder** (contexto
isolado, mesmo padrao). Para ajustes pontuais (so falta a Policy, ou um Request), faca inline.

## Regra de ouro

Para algo grande ou ambiguo, **pergunte antes** de gerar tudo (regra do CLAUDE.md). Nunca introduza
pacotes fora do stack aprovado (spatie permission/data/activitylog, dompdf).

## Saida

- Arvore de arquivos criados (1 linha por arquivo).
- Pontos `// TODO:` que exigem decisao de negocio.
- Proximos passos: migration aplicada? testes? rota testada?
