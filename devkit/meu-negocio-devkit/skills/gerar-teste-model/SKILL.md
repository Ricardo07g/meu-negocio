---
name: gerar-teste-model
description: "Gera teste Feature + factory (PHPUnit) para um Model/fluxo do Meu Negocio no padrao do projeto. Use quando o usuario pedir para testar/cobrir um model, modulo ou fluxo (ex.: 'escreve testes pro Estoque', 'cobre o Produto com testes', 'falta factory de Despesa'), especialmente os modulos hoje sem cobertura."
argument-hint: "<NomeDoModel ou modulo> (ex.: Produto, Despesa, MovimentoEstoque)"
---

# Gerar teste + factory — Meu Negocio

Produz testes que **passam de verdade** e seguem as convencoes da suite existente. O alvo e
`$ARGUMENTS` (um Model, modulo ou fluxo). Se vazio, pergunte o que cobrir.

## Fundamentos da suite (nao reinvente)

- **Execucao no Docker**: `docker exec meu-negocio-app php artisan test --filter=<NomeDoTeste>`.
  Sem `php` no host.
- **Banco**: SQLite in-memory (`phpunit.xml`) + trait `RefreshDatabase`. Cuidado com SQL so-MySQL.
- **Base de contexto**: trait `tests/Concerns/CriaTenant.php`:
  `criarRedeAutenticada()`, `criarRede($sufixo)`, `criarUsuarioComum($rede,$empresa,$papel)`,
  `garantirSeedsBase()`.
- **Permissoes Spatie**: apos criar roles/permissions no teste, chame
  `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()` antes de exercitar.
- **Local**: `tests/Feature/<Modulo>/NomeTest.php`. Factories em `database/factories/`.

## Passos

1. **Leia o alvo** (Model + Controller/Service/Action) e um teste vizinho similar para copiar estilo
   e descobrir rotas/nomes reais (nao invente endpoints). Veja `padroes-projeto` se precisar.
2. **Factory**: os models **nao usam `HasFactory`** (namespace modular `App\Modules\...`), entao
   `Model::factory()` NAO funciona — instancie sempre pela classe da factory:
   `ClienteFactory::new()->create([...])`. Referencias aninhadas tambem (`RedeFactory::new()`).
   Ja existem factories para os models principais em `database/factories/` (catalogo, financeiro,
   caixa, estoque, agenda); reutilize-as. Se faltar alguma, crie no mesmo estilo (states, relacoes,
   `rede_id`/`empresa_id` explicitos quando o teste roda sem auth).
3. **Casos minimos** a cobrir, conforme o tipo:
   - CRUD: criar, listar (com escopo), atualizar, excluir.
   - **Isolamento multi-tenant**: rede/empresa A nao ve dados de B (modelo: `MultiTenant/IsolamentoTest`).
   - **Autorizacao**: caminho autorizado + 403 sem permissao (modelo: `Pagamento/PermissoesTest`).
   - **Financeiro** (Pagamento/Despesa/Caixa): status de parcela, valores, movimentos de caixa.
   - **Smoke de tela** (Dashboard/index): 200 + dados-chave presentes.
4. **Rode ate verde**: `docker exec meu-negocio-app php artisan test --filter=...`. Itere.
5. **Formate**: `docker exec meu-negocio-app vendor/bin/pint <arquivos>`.

## Se um teste revelar bug real

Nao mascare o teste para passar. Descreva o bug e pergunte antes de alterar codigo de producao
(so testes/factories sao livres para mexer).

## Para volume grande

Delegue ao subagente **laravel-test-writer** (contexto isolado) e, para varios modulos
independentes, dispare em paralelo. Sempre cole a saida real do `php artisan test` verde na resposta.
