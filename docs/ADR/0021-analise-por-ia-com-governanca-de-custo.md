# ADR-0021 — Análise por IA: o SQL calcula, o modelo redige, a cota governa

- **Status:** Aceito
- **Data:** 2026-08-29
- **Contexto:** módulo `Ia` + carteira RFM (`app/Modules/Cliente`)

## Contexto

O sistema acumula dados que o lojista não consegue ler sozinho: quem some, quem volta, quem
concentra a receita. Queríamos usar IA para isso sem cair em três armadilhas conhecidas.

**Primeira: virar canal de saída.** A ideia inicial era gerar mensagens de cobrança e lembrete
prontas para enviar ao cliente final. Foi descartada por responsabilidade, não por dificuldade:
texto livre gerado pela plataforma e endereçado ao consumidor é um vetor de abuso direto — um
golpista escreve "sua conta foi bloqueada, pague aqui", o sistema redige bem e entrega. O operador
da plataforma descobriria tarde e seria co-responsável.

**Segunda: pedir conta ao modelo.** LLM barato erra número com confiança. Perguntar "quais clientes
estão em risco e quanto isso representa" produz resposta plausível e falsa.

**Terceira: custo sem freio.** Um botão de "analisar" clicável à vontade multiplica gasto sem
multiplicar valor — a resposta para um dado que não mudou é a mesma.

## Decisão

**1. Toda IA aqui é de consumo interno.** Nada gerado por modelo sai da plataforma para o
consumidor final. A superfície é a tela do lojista.

**2. O PHP calcula, o modelo redige.** A segmentação RFM é SQL determinístico
(`SegmentacaoRfmService`); o modelo recebe o resultado **já classificado** e só nomeia, explica e
sugere ação. Ele não recebe uma data, um id ou um valor exato — recebe contagens, percentuais e
médias arredondadas. Isso derruba o custo *e* elimina a classe de erro em que o modelo inventa
número.

**3. Um contrato, drivers trocáveis.** `App\Modules\Ia\Contracts\Ia` recebe instruções + dados +
schema e devolve DTO tipado. Três implementações: **Workers AI** (padrão), **Gemini** (escape hatch)
e **Fake** (a suíte). Molde do `Turnstile`: sem credencial, a feature se declara desligada e ninguém
toca a rede — dev, CI e testes rodam sem chave.

**4. Cache por hash do payload, não por eventos.** A chave é o `sha256` do pacote mastigado (mais
modelo e versão do prompt). Invalidação por listener de "venda nova" exigiria hook em venda de
produto, venda de etapas, agendamento, baixa e estorno — e o dia que um for esquecido, o cache fica
velho para sempre sem erro visível.

**5. Franquia diária de análises por empresa** (`planos.limite_analises_ia_dia`, 10 no Pro), contada
da própria tabela (sem contador paralelo) e **visível ao lado do botão que a gasta**.

A unidade é *análise*, não *token*, embora token seja o que custa: "8 de 10 análises hoje" significa
algo para quem toca um salão, "38.400 de 50.000 tokens" não significa nada. O payload aqui tem
tamanho previsível (no máximo 6 segmentos), então contar análises é bom procurador do custo. Tokens e
custo seguem gravados — só não são eles que barram.

Duas coisas **não** consomem franquia, de propósito: **cache hit** (reaproveitar não cria linha, então
reanalisar carteira que não mudou é grátis — é o que torna o botão seguro de clicar) e **erro do
provedor** (falha nossa não se cobra do lojista; o laço de retentativa é barrado pelo `throttle` da
rota). Ambas ficam registradas para medição.

## Consequências

### Positivas
- Uma tabela (`analises_ia`) serve de cache, histórico e razão de consumo. A taxa de reaproveitamento
  vira métrica de primeira classe: mostra se o desenho está funcionando.
- A tela da carteira **tem valor com a IA desligada** — a segmentação é SQL. Provedor fora do ar,
  cota estourada ou plano sem a feature degradam o texto interpretativo, nunca a página.
- Trocar de provedor é uma linha de `IA_DRIVER`. Nenhum service, view ou teste conhece o provedor.
- A suíte nunca toca a rede nem depende de credencial.

### Negativas
- O payload precisa ser **arredondado** para o hash ser estável: um ticket médio de R$ 147,32 mudaria
  a cada venda e o cache nunca acertaria. Consequência aceita: o modelo fala em "cerca de", enquanto
  a tela mostra o número exato vindo do SQL.
- A Cloudflare **não garante** aderência a schemas complexos (devolve erro quando o modelo não
  cumpre), ao contrário do `responseSchema` do Gemini, que é imposto pelo servidor. Por isso o schema
  é achatado e o driver Gemini existe desde o primeiro dia.
- Contar análises em vez de tokens perde precisão de custo: uma análise atípica pesa igual a uma
  leve. Aceito porque o payload é de tamanho limitado por construção, e a legibilidade para o usuário
  vale mais que o centavo de variação.

- Temperatura fixada em 0.2: este é um relatório, não um texto criativo. Duas leituras da mesma
  carteira inalterada devem dizer a mesma coisa — variar o texto a cada clique faz o usuário
  desconfiar do número, não achar o texto interessante.
- `max_tokens` é obrigatório e explícito: sem ele a Cloudflare aplica um default baixo e corta o
  JSON no meio de uma string, e o driver recusa por "formato inválido" — sintoma que não aponta
  para o tamanho.

### Neutras
- A cota reinicia à **meia-noite de São Paulo**, não em UTC. O app roda em UTC, então contar o dia com
  `today()` zeraria a cota às 21h — no meio do expediente de quem paga por ela.
- `planos.limite_analises_ia_dia` é uma coluna só: `0` já significa "sem IA" e `Plano::temIa()` deriva
  a flag. Duas colunas dizendo a mesma coisa acabam divergindo.
- Com 10 análises/dia e o modelo padrão (~88 neurônios por análise), **cerca de 11 unidades no limite
  máximo ainda cabem na cota gratuita** de 10.000 neurônios/dia da Cloudflare.
- A viabilidade de cada canal (WhatsApp, e-mail, balcão) é **calculada no PHP** e entregue pronta
  como `usar: true/false`. Pedir ao modelo que conclua "1 e-mail cadastrado em 47 clientes significa
  não sugerir e-mail" é pedir raciocínio numérico — exatamente o que ele faz mal e o que esta
  arquitetura tira dele. Sem isso ele recomendava disparo de e-mail para uma base sem e-mail.
- As descrições de segmento falam de **oportunidade, nunca de ameaça**: cliente que parou de vir é
  receita a recuperar, não risco a conter. A diferença muda o que o leitor faz depois de ler.
- Os rótulos dos segmentos ("Alto valor", "Recorrentes", "Inativos") são deliberadamente sóbrios.
  "Campeões" e "Sumidos" são os termos de manual de marketing, mas esta tela é um relatório que o
  dono do negócio pode imprimir e mostrar ao contador.
- A permissão `ia.analisar` é separada de `ia.ver`: gerar gasta cota da unidade, consultar o resultado
  guardado não.

## Alternativas consideradas

- **Job assíncrono + polling** (como as exportações, ADR-0012): descartado porque a análise volta em
  segundos com o usuário olhando a tela. AJAX direto é a UX certa; fila seria máquina demais.
- **Gemini como padrão**: mais previsível na saída estruturada, mas o projeto já tem conta Cloudflare
  (R2, Turnstile, cron) e 10.000 neurônios/dia grátis cobrem o volume. Um fornecedor a menos venceu.
- **Quintis no RFM**: com base pequena, o quintil de cada cliente oscila a cada venda — instável para
  o usuário e péssimo para o cache. Faixas fixas são explicáveis e estáveis.
