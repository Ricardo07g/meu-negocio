# ADR-0012 — Exportações assíncronas via fila + download autenticado

## Status

Aceito — julho/2026. Primeiro **job de fila**, primeiro **worker** no Docker e primeiro **download
autenticado** do projeto. Estabelece o padrão para exportações/relatórios pesados.

## Contexto

A tela `contas/{conta}/extrato` mostrava os últimos 100 lançamentos, sem filtro nem forma de ver
períodos maiores. O dono pediu: **mês na tela** (leve, o dia a dia) e, para períodos maiores, uma
**planilha (CSV/XLSX) baixável**, cuja geração deveria rodar **num job** — para não montar um extrato
grande de forma síncrona na request (poupa memória/CPU).

Infra pré-existente: fila = driver `database` (MySQL), tabelas prontas, mas **nenhum job** e **nenhum
worker** rodando no Docker. Storage `r2` (ADR-0008) funcional, mas o módulo Arquivo só entrega por
**URL pública** — inadequado para extrato (dado financeiro). Só havia `dompdf` (sem lib de planilha).
Sem padrão de notificação "arquivo pronto". Jobs não têm `auth()`/`session()`, logo os global scopes
de tenancy (`RedeTrait`/`EmpresaTrait`) não filtram/preenchem sozinhos (ADR-0004).

## Decisão

1. **Tela = 1 mês; período maior = exportação.** `extrato` passa a filtrar por `?mes=YYYY-MM` (default
   mês atual), com navegação prev/próximo e totais do mês. Sem `limit` (um mês é limitado).

2. **Exportação assíncrona.** Um `POST contas/{conta}/exportar` cria um registro **`Exportacao`**
   (`status = processando`) e **enfileira** o job `GerarExportacaoExtrato`. A request retorna na hora.
   A tela lista as "Exportações recentes" com status (badge) e, quando pronta, o botão **Baixar**; a
   página se **auto-atualiza** (reload leve) enquanto houver exportação processando (polling simples,
   já que não há padrão de notificação).

3. **Planilha via `openspout/openspout`** (não `maatwebsite/excel`, incompatível com Laravel 13):
   agnóstico de framework e **escreve CSV e XLSX em streaming** (memória baixa — o motivo do job).
   CSV usa `;` + BOM (Excel pt-BR); XLSX grava o valor como número real (célula somável). O escritor
   itera a query em **chunks** de 1000.

4. **Job com tenancy explícita.** O worker não tem auth/session, então o job recebe só o `exportacaoId`
   e resolve tudo com `withoutGlobalScopes()` + `where('rede_id')`/`where('empresa_id')`/`where('conta_id')`
   (padrão do `CaixaService`; ADR-0004). Grava o arquivo num path **privado** no R2
   (`sistema/redes/{rede}/empresas/{empresa}/exportacoes/{uuid}.{ext}`) e atualiza a `Exportacao`
   (status/caminho/tamanho). Falha → `status = erro` + mensagem.

5. **Download autenticado (nunca URL pública).** `GET contas/{conta}/exportacoes/{exportacao}/baixar`:
   o binding de `{exportacao}` já é escopado por empresa (EmpresaTrait); checa-se ainda que a exportação
   é **desta conta** e está **pronta**, e entrega via `Storage::disk(...)->download(...)` (streamed,
   autenticado). Difere do módulo Arquivo (link público) — extrato é dado sensível.

6. **Worker no Docker.** Um serviço `queue` no `docker-compose.yml`
   (`php artisan queue:work database --tries=3 --sleep=3 --max-time=3600`) consome a fila. Sem ele, um
   `dispatch` ficaria parado na tabela `jobs`.

## Consequências

### Positivas
- Request leve: períodos grandes não travam nem estouram memória na web (vão para o worker, em streaming).
- Planilha nativa (CSV **e** XLSX) para o lojista, sem link público de dado financeiro.
- Padrão reutilizável de exportação assíncrona (job + status + download autenticado) para futuros
  relatórios.

### Negativas / limites
- **Requer o worker rodando** (`docker compose up -d queue`) — nova peça de operação.
- Entrega por **polling** (auto-refresh), não notificação push/e-mail (fora de escopo v1).
- Arquivos de exportação acumulam no R2 — **limpeza automática** (comando agendado) fica como próximo
  passo (espelhando `LimparRascunhosArquivo`).
- Nova dependência: `openspout/openspout`.

### Neutras
- `Exportacao` é transacional (empresa-level, `BaseModel + EmpresaTrait`), como Conta/Caixa.
- `darBaixaParcelaPagamento` e o resto do financeiro não são afetados.

## Verificação
Testes: `exportar` cria pedido processando + enfileira (`Bus::fake`); job gera CSV e XLSX válidos e
marca pronto (`Storage::fake('r2')`, filtro de período); download autenticado devolve o arquivo e
**nega** exportação de outra conta / ainda processando (404). Ver `tests/Feature/Conta/ExportacaoExtratoTest.php`.

## Atualização (2026-07-22) — retenção, limpeza, exclusão e AJAX

Fechando os "próximos passos" que estavam acima em *Negativas/limites*:

- **Retenção de 1 dia + limpeza horária.** `Exportacao::DIAS_RETENCAO = 1` (+ `expiraEm()`). Comando
  `exportacoes:limpar` (`LimparExportacoes`) agendado **de hora em hora** (`routes/console.php`) apaga o
  arquivo no storage **e** o registro dos expirados (`withoutGlobalScopes`, varre todas as redes/empresas).
  Espelha o `arquivos:limpar-rascunhos`. → arquivos não acumulam mais. **Requer o serviço `scheduler`**
  (`schedule:work`) no docker-compose — sem ele, nenhum `Schedule::command` dispara (gap que também
  afetava o `arquivos:limpar-rascunhos` diário e agora está coberto).
- **Exclusão manual.** `DELETE contas/{conta}/exportacoes/{exportacao}` (`excluirExportacao`): apaga
  arquivo + registro, 404 cross-conta. Botão 🗑 por linha. O job faz `find` (não `findOrFail`) — se a
  exportação for excluída antes do worker pegar, ele sai sem erro.
- **UX por AJAX (não mais reload).** `GET .../exportacoes/status` (JSON) alimenta um **polling** leve na
  tela: atualiza badges, coluna **Expira** e libera **Baixar** sem recarregar a página; para sozinho
  quando nada está processando. O JS re-liga o `data-confirm` (SweetAlert) nas linhas que injeta.
- **Interface deixa claro** que cada arquivo dura 1 dia (nota no card + coluna "Expira" por linha).
