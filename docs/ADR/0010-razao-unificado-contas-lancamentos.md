# ADR-0010 — Razão unificado: contas financeiras e lançamentos

## Status

Aceito — julho/2026. Evolui o ADR-0009 (não o substitui: recebíveis de cartão continuam válidos, agora com destino em conta).

## Contexto

Depois do ADR-0009, o sistema tinha **dois lugares** onde o dinheiro "existia", e nenhum representava o ciclo financeiro por completo:

1. **Caixa físico** (`caixas` + `movimentos_caixa`): a gaveta do dia. Dinheiro **e Pix** caíam aqui como entrada — mas o Pix, na vida real, **não entra na gaveta**: ou cai direto na conta bancária do lojista, ou vem de uma **maquineta** (adquirente).
2. **Recebível** (`recebiveis`): dinheiro de cartão a receber do banco. Quando "vencia" (data prevista ≤ hoje), só mudava de *status* — **o dinheiro não caía em lugar nenhum**: não virava saldo, não entrava em conta. Sumia conceitualmente.

Não existia conceito de **conta bancária**. Não dava para responder "quanto tenho na conta do banco?" nem "quanto de cartão já caiu vs. está pra cair?". O eixo de decisão da baixa (`CaixaService::aplicarBaixaParcela`) olhava só `forma->gera_recebivel`: `false` ⇒ gaveta; `true` ⇒ recebível. Não havia como rotear o Pix para uma conta que não fosse a gaveta.

Alternativas consideradas: (a) só rotear o Pix para fora da gaveta com um registro leve novo — rejeitada por inventar um terceiro conceito onde já havia o esqueleto de `Conta`/`Lancamento`; (b) razão unificado (esta decisão).

## Decisão

**Tudo é conta; todo movimento é um lançamento (crédito/débito). O caixa físico é apenas a conta do tipo `caixa`.**

1. **`contas`** (empresa-level, soft delete): onde o dinheiro fica. `tipo` = `caixa` (gaveta) / `banco` / `carteira` (adquirente/carteira digital). Flags `eh_caixa_padrao` e `eh_destino_recebivel_padrao`. Toda empresa nasce com uma conta Caixa + uma Conta Bancária (via `CriarEmpresaAction`, ao lado das formas).

2. **`lancamentos`** (o razão, empresa-level, append-only): toda entrada (crédito) ou saída (débito) numa conta. `conta_id` (obrigatório), `caixa_id` (nullable — setado só nos lançamentos da conta-caixa, ligando a sessão diária), `categoria` (`movimento`/`sangria`/`reforco`/`estorno`/`transferencia`/`ajuste`), origem por FK (`baixa_pagamento_id`/`baixa_despesa_id`). **Substitui o `movimentos_caixa`**. `saldo da conta = saldo_inicial + Σcréditos − Σdébitos`.

3. **A forma de pagamento roteia para uma conta destino.** `formas_pagamento.conta_destino_id` (FK nullable → `contas`). Quando null, o motor resolve pela natureza: se gera recebível ⇒ conta `eh_destino_recebivel_padrao`; senão dinheiro/boleto/crediário ⇒ conta `eh_caixa_padrao`, Pix-direto ⇒ conta destino de recebível padrão. Como forma e conta são ambas empresa-level, a forma aponta direto para a conta (sem acoplar níveis).

4. **O eixo de decisão da baixa muda** de "gera recebível?" para **"qual a conta destino `C`, e ela é do tipo caixa?"**. Regra única: **exige caixa aberto ⟺ (`!gera_recebivel` E `C.tipo === caixa`)**. Uma baixa grava **um `Lancamento`** (imediato, na conta destino) **OU** **N `Recebivel`** (diferido) — nunca ambos. O `Recebivel` ganha `conta_id` e entra no saldo da conta **pela data prevista** (`data_prevista ≤ hoje`, não cancelado), sem virar lançamento e sem job.

5. **Pix é recebível configurável** (evolução do ADR-0009): pode ser **direto ao banco** (imediato, D+0, sem taxa, vira crédito na conta banco) ou **via maquineta/adquirente** (`gera_recebivel = true`, D+N com taxa, vira recebível na conta carteira/banco). Só o Pix tem esse toggle; os demais tipos têm comportamento fixo. Antecipação continua só para cartões.

6. **O caixa diário vira uma sessão da conta-caixa.** `caixas.conta_id` aponta para a conta `eh_caixa_padrao`. O ritual (abrir/fechar/reabrir, contagem de gaveta via `saldo_abertura`/`saldo_fechamento`, sangria/reforço) permanece — mas os movimentos viram `Lancamento` com `caixa_id` setado. `saldo_abertura`/`saldo_fechamento` continuam campos de contagem física (não viram lançamento, para não contar em dobro).

7. **Estorno é contra-lançamento.** O discriminador deixa de ser `baixa->caixa_id === null` (que confundiria Pix-direto com cartão) e passa a ser **existência de recebível**: com recebível ⇒ cancela os recebíveis; sem recebível ⇒ gera um `Lancamento` de sinal oposto (`categoria = estorno`) na conta de origem, preservando o guard de caixa fechado quando a origem é a conta-caixa.

**Migração:** ambiente local, reseed aceitável; a migration de `caixas.conta_id` faz backfill idempotente a partir de `eh_caixa_padrao`. Os `movimentos_caixa` históricos não são convertidos (reseed).

## Consequências

### Positivas
- **Ciclo financeiro correto**: Pix cai na conta certa (banco/carteira), não na gaveta; cartão vira dinheiro real na conta quando liquida (por data). Dá para responder "quanto tenho em cada conta" e "quanto está a caminho".
- **Um só razão**: caixa, banco e carteira compartilham o mesmo modelo (`Lancamento`); extrato e saldo por conta saem de graça.
- **Sem job**: recebíveis "a caminho" vs. "disponível" continuam derivados por data.
- **Pix fiel à realidade**: cobre tanto o Pix-chave-direto quanto o Pix-na-maquineta (com taxa/D+N).

### Negativas
- **Raio de impacto grande**: reescreve o motor de baixa/estorno, o caixa (agora sessão da conta) e boa parte dos testes financeiros que assertavam `movimentos_caixa`. Mitigado por PHPStan + suíte e por fatiamento (schema → motor → remoção do `MovimentoCaixa`).
- **Risco de contagem dupla**: exige a invariante "uma forma gera Lançamento **ou** Recebível, nunca ambos; recebível nunca vira Lançamento". Coberto por teste dedicado.
- **Tenancy do `conta_destino_id`**: o `Rule::exists` cru ignora global scopes; a validação e a resolução da conta no motor filtram `rede_id`/`empresa_id` na mão (mesmo padrão do `forma_pagamento_id`).

### Neutras
- `saldo_abertura`/`saldo_fechamento` do caixa permanecem como contagem física da gaveta (reconciliação), separados do razão cumulativo da conta-caixa.
- Transferências entre contas (banco↔caixa) e despesa debitando banco ficam reservadas (`categoria = transferencia`) para uma fase seguinte.
- Faturamento continua por competência (soma de `BaixaPagamento`), independente de quando o dinheiro cai na conta.

## Atualização (2026-07-19) — Trilhos de conta (Fase B.1)

Revisão de UX/consistência: a configuração de contas estava sem guarda-corpos. Decisões, sem migration (tudo em nível de app):

1. **Conta Caixa = do sistema, 1 por empresa, travada mas renomeável.** `Conta::ehProtegida()` ⟺ `tipo === Caixa`. O tipo `Caixa` não é ofertado no form (o lojista só cria `Banco`/`Carteira`); a Caixa não muda de tipo, não inativa nem exclui — só renomeia. As flags `eh_caixa_padrao`/`eh_destino_recebivel_padrao` viraram **internas** (só o seed marca) e saíram do form.
2. **Excluir vs. inativar.** `ContaService::excluir` só apaga (soft) conta sem movimentações (`lancamentos`/`recebiveis`) **e** sem vínculo (forma/caixa); caso contrário orienta a inativar. Nova ação **`inativar`/`reativar`** (`PATCH contas/{conta}/inativar|reativar`), que bloqueia a Caixa e a conta com forma **ativa** vinculada. Espelha `PerfilAcessoService::excluir` (via `NegocioException` + `TratamentoErros`).
3. **Recebível mora na forma, não numa conta global.** `conta_destino_id` passa a ser **obrigatório em cartão débito/crédito e Pix** (`TipoFormaPagamento::exigeContaDestino()`) e **não pode ser a conta Caixa** (cartão/pix nunca caem na gaveta). Cada maquineta (Cielo, Rede, Pix direto...) é uma forma com sua conta. O `semearPadrao` das formas liga cartão/pix à Conta Bancária — por isso **as contas são semeadas antes das formas** em `CriarEmpresaAction`/seeders/`CriaTenant`.
