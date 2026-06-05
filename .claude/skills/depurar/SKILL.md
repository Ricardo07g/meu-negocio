---
name: depurar
description: "Depuracao sistematica de bugs no Meu Negocio (Laravel/multi-tenant): reproduzir, isolar, hipotese, testar, corrigir na raiz com teste de regressao. Use quando algo 'nao funciona', da erro/500/403/422 inesperado, teste fica vermelho, ou ha comportamento estranho de tenancy/caixa/parcela/permissao — em vez de chutar correcoes."
---

# Depurar — Meu Negocio

Bug e investigacao, nao adivinhacao. Uma hipotese por vez, confirmada por evidencia antes de corrigir.

## Metodo
1. **Reproduzir**: identifique o gatilho exato (rota, comando, teste). Sempre que der, escreva um
   **teste que falha** reproduzindo o bug — vira a prova de que a correcao funcionou.
   `docker exec meu-negocio-app php artisan test --filter=<NomeDoTeste>`.
2. **Coletar evidencia**: leia a stack real (`storage/logs/laravel.log`), a saida do teste, e
   classifique o erro: tenancy (escopo rede/empresa), autorizacao (403 via Policy), validacao (422),
   `NegocioException` (regra de negocio), ou bug logico. Use `php artisan tinker` para inspecionar
   estado: `docker exec -i meu-negocio-app php artisan tinker`.
3. **Isolar**: percorra o fluxo `Controller -> Service -> Action -> Model` por bissecao. Confirme em
   qual camada o valor diverge do esperado.
4. **Hipotese unica**: formule UMA causa provavel e teste-a (log, asserção, tinker) antes de mexer.
5. **Corrigir na causa raiz** (nao no sintoma). Rode o teste do passo 1 ate verde + a suite do modulo.
6. **Validar**: feche com a skill `validar-implementacao`.

## Suspeitos comuns deste projeto
- **Teste passa no MySQL e falha no SQLite** (suite roda em SQLite in-memory) — SQL/feature so-MySQL.
- **`Model::factory()` quebra**: models NAO usam `HasFactory` (namespace modular) — use
  `XxxFactory::new()->create([...])`.
- **403 inesperado**: Policy nao registrada em `AppServiceProvider`, permissao faltando, ou cache —
  `app(PermissionRegistrar::class)->forgetCachedPermissions()` nos testes.
- **Dado "some" / aparece de outra empresa**: global scope de `rede_id`/`empresa_id`, contexto
  `empresa_contexto_atual` vs `empresas_atuais`, ou `withoutGlobalScope` indevido — veja
  `.claude/rules/multi-tenant-seguranca.md`.
- **Parcela "vencida" mas status Pendente**: `statusEfetivo()` deriva em leitura; o persistido nao
  muda sozinho (`.claude/rules/modelo-financeiro.md`).
- **Baixa falha**: exige **caixa aberto** no dia/empresa.

## Saida
A causa raiz (com evidencia), a correcao aplicada, e o teste de regressao verde (saida real colada).
