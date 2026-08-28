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
| [0007](0007-assinatura-faturamento.md) | Assinatura, faturamento mensal e troca de plano pro-rata | Parcialmente substituído por ADR-0013 |
| [0008](0008-armazenamento-de-arquivos-r2.md) | Armazenamento de arquivos (imagens/anexos) no Cloudflare R2 | Aceito |
| [0009](0009-formas-pagamento-configuraveis-e-recebiveis.md) | Formas de pagamento configuráveis e recebíveis de cartão | Aceito |
| [0010](0010-razao-unificado-contas-lancamentos.md) | Razão unificado: contas financeiras e lançamentos | Aceito |
| [0011](0011-fluxo-nao-saldo-recebimentos-por-forma.md) | Fluxo, não saldo: recebimentos por forma no caixa do dia | Aceito |
| [0012](0012-exportacoes-assincronas-fila-download-autenticado.md) | Exportações assíncronas via fila + download autenticado | Aceito |
| [0013](0013-licenca-por-empresa.md) | Licença por empresa: o plano é da unidade, não da rede | Aceito |
| [0014](0014-caixa-do-dia-mostra-o-fluxo-nao-o-razao.md) | O caixa do dia mostra o fluxo da loja, não o razão da gaveta | Aceito |
| [0015](0015-normalizacao-de-imagens-em-webp.md) | Normalização de imagens em WebP na gravação | Aceito |
| [0016](0016-agendador-por-cron-http-externo.md) | Agendador por cron HTTP externo (Cloudflare Workers) | Aceito |
| [0017](0017-frankenphp-no-lugar-do-artisan-serve.md) | FrankenPHP no lugar do `php artisan serve` em produção | Aceito |
| [0018](0018-agendamento-e-operacao-venda-e-financeiro.md) | Agendamento é operação, venda é financeiro: a cobrança acontece na finalização | Aceito |
| [0019](0019-expediente-da-unidade-e-encaixe-autorizado.md) | Expediente da unidade: fora do horário é encaixe autorizado, não acidente | Aceito |
| [0020](0020-email-transacional-pela-api-do-resend.md) | E-mail transacional pela API do Resend, não por SMTP | Aceito |

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
