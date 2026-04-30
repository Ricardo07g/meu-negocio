# Architecture Decision Records (ADR)

Esta pasta documenta as **decisões arquiteturais marcantes** do Meu Negócio, no formato MADR-light: Status / Contexto / Decisão / Consequências (positivas, negativas, neutras).

ADRs são imutáveis após aceitos. Mudanças de rumo viram um novo ADR que **substitui** o anterior, sem editar o original.

## Índice

| # | Título | Status |
|---|--------|--------|
| [0001](0001-multi-tenant-single-db.md) | Multi-tenant single-DB com `rede_id` | Aceito |
| [0002](0002-modelo-financeiro-titulo-parcela-baixa.md) | Modelo financeiro Título + Parcela + Baixa | Aceito |
| [0003](0003-estrutura-modular.md) | Estrutura modular em `app/Modules/` | Aceito |
| [0004](0004-base-model-traits-tenancy.md) | `BaseModel` + Traits para tenancy | Aceito |
| [0005](0005-caixa-diario-com-retroativo.md) | Caixa diário com abertura retroativa permitida | Aceito |
| [0006](0006-foreign-keys-cascade.md) | Comportamento de foreign keys (cascade / null / restrict) | Aceito |

## Como ler

Cada ADR tem 4 seções:

- **Status** — `Aceito`, `Substituído por ADR-XXXX`, ou `Descontinuado`.
- **Contexto** — o cenário que levou à decisão. Restrições, alternativas consideradas.
- **Decisão** — o que foi escolhido, em poucas linhas. Sem ambiguidade.
- **Consequências** — impactos positivos, negativos e neutros, registrados honestamente.

## Como contribuir com um novo ADR

1. Copiar um existente como template.
2. Numerar sequencialmente (`000X-titulo-curto.md`).
3. Adicionar à tabela de índice acima.
4. Abrir PR para discussão antes de mergear.
