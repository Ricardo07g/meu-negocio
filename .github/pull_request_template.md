<!--
Mergear este PR publica em producao (Railway, deploy por git a partir da main).
Preencha o que se aplica e apague o resto — checklist e para pensar, nao para decorar.
-->

## O que muda

<!-- O problema antes do codigo: o que estava errado ou faltando, e por que agora. -->

## Como verificar

<!-- Comandos e o que esperar. Cole a saida real, nao a intencao.
     Porta local: /pre-pr  (Pint + PHPStan + testes no container). -->

```
docker exec meu-negocio-app php artisan test
docker exec meu-negocio-app vendor/bin/phpstan analyse --no-progress
docker exec meu-negocio-app vendor/bin/pint --test
```

## Checklist

<!-- A régua completa está em .claude/rules/testes-por-feature.md -->

- [ ] Testes que a régua exige: isolamento (dado tenant-aware) · 403 (rota mutável) · estorno (dinheiro) · um por regra de recusa
- [ ] `/pre-pr` verde localmente
- [ ] Se mexeu em dado tenant-aware: `/auditar-tenancy` no diff
- [ ] Se mexeu em **JS de tela**: validado com clique real no browser, nao so por teste HTTP
- [ ] Se mexeu em `.claude/`: `bash bin/sync-devkit.sh` rodado
- [ ] Se a decisao tem trade-off duradouro: ADR em `docs/ADR/`
- [ ] Docs afetados atualizados (`CLAUDE.md`, `.claude/rules/`, `README.md`)

## Risco

<!-- O que pode quebrar em producao e como reverter. "Nenhum" e uma resposta valida,
     desde que seja verdade. Migration destrutiva, mudanca de Policy, alteracao de
     preco/plano e mexida em fluxo de caixa merecem uma linha aqui. -->
