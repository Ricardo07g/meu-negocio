# ADR-0013 — Licença por empresa: o plano é da unidade, não da rede

## Status

Aceito — julho/2026. Substitui parcialmente o [ADR-0007](0007-assinatura-faturamento.md)
(a unidade comercial e os limites mudam; o rateio pro-rata na fatura do mês permanece,
e será revisto quando a fatura ganhar itens por unidade).

## Contexto

O `Plano` nascia preso à `Rede` (`redes.plano_id`), com `max_empresas` "concedendo" N empresas
ao tenant — inclusive ilimitadas no plano `business`. Três problemas apareceram juntos:

1. **A régua não era monotônica.** O plano `free` (R$ 0) tinha `tem_financeiro = true` e o
   `basic` (R$ 49,90, pago) tinha `tem_financeiro = false`. Pagar **removia** um módulo.
2. **A unidade comercial estava errada.** O negócio cobra por unidade atendida (clínica, salão,
   filial), não por "conta". Um plano que concede empresas convida o tenant a cadastrar unidades
   sem contrapartida — o custo de operação cresce e a receita não.
3. **Não havia trial.** Toda conta nascia no `free` para sempre e, como o `free` dava quase tudo,
   não havia nem demonstração de valor nem motivo de upgrade.

Restrição herdada: há **uma fatura por mês por rede** (`unique(rede_id, referencia)` em `faturas`),
e não existe gateway de pagamento — a cobrança é simulada.

## Decisão

### O plano desce de `redes` para `empresas`

`empresas.plano_id` passa a existir e `redes.plano_id` é removida. A rede deixa de ser a unidade
comercial e vira apenas o agrupamento das licenças de um mesmo dono. Não se mantêm as duas colunas:
duas fontes para o mesmo fato divergem.

Consequentemente **`planos.max_empresas` deixa de existir**, e com ela a convenção `0 = ilimitado`
que estava espalhada por seis pontos do código. Um plano não concede unidades: ele é comprado
*para* uma unidade. `max_usuarios` passa a contar assentos **por empresa**, unindo o pivot
`empresa_usuario` aos usuários que têm a unidade como default (`Empresa::contarUsuarios()`) — o
Admin criado no registro só aparece pelo segundo caminho e ficaria de fora se contássemos só o pivot.

### Feature flags resolvem pela empresa em contexto

`tem_estoque` e `tem_financeiro` passam a depender da unidade em que o usuário está operando —
duas unidades da mesma rede podem estar em planos diferentes. A resolução reusa a cadeia já
adotada em Venda, Agenda, FormaPagamento e Conta, encapsulada em `App\Support\PlanoVigente`:

```
ContextoEmpresa::resolver() ?? usuario->empresa_id
```

Não há caso "nenhuma empresa": o middleware `verificar.empresa` roda antes.

### Dois planos, o gratuito subconjunto estrito do pago

| | Grátis | Pro |
|---|---|---|
| preço | R$ 0 | R$ 79,90 por licença/mês |
| usuários na unidade | 2 | 15 |
| Clientes / Serviços / Produtos / Agenda / Vendas | ✔ | ✔ |
| Estoque / Financeiro | ✘ | ✔ |

`basic` e `business` saem. A migration reaponta antes de remover (planos pagos sobem para Pro —
rebaixar tiraria módulos de quem já pagava). `planos.slug` vira a chave técnica estável e `nome`
fica só para exibição; `preco_mensal` vira `preco_por_licenca` porque a semântica mudou.
(`tem_relatorios`, criada na migration original e nunca usada, já havia sido removida em
`2026_04_25_000001_remove_tem_relatorios_from_planos`.)

**O Grátis vale para uma única unidade por rede** — a do registro, depois do trial. Sem essa regra,
bastaria abrir N unidades gratuitas para ter a rede inteira de graça.

### Trial é estado da licença, não um terceiro plano

`empresas.trial_expira_em` (date, nullable). A primeira unidade da rede nasce **no Pro** com 14 dias
(`Empresa::DIAS_DE_TRIAL`); unidades contratadas depois já nascem pagas — trial é aquisição, não
brinde por unidade. Como durante o teste a unidade *está* no Pro de verdade, nada muda em
`ValidarPlanoAction`, `VerificarPlano` ou nos gates de menu.

O encerramento é do comando `assinaturas:expirar-trial`, agendado `daily()`, com uma rede de
segurança em `VerificarEmpresa` (uma vez por sessão por dia) para a conta não ficar presa no Pro
se o scheduler cair. O rebaixamento acontece mesmo se a unidade excedeu os limites do Grátis
durante o teste: nada é apagado, apenas para de ser possível criar mais.

### Contratar unidade sai da UI do tenant

`Route::resource('empresas', ...)` fica em `only(['index','edit','update'])`. O tenant consulta suas
licenças, edita dados cadastrais e pode fazer **upgrade** de uma unidade de Grátis para Pro
(único caminho de receita self-service). Contratar unidade nova, cancelar e fazer downgrade são
atos comerciais do operador do SaaS — o painel de superusuário consumirá `CriarEmpresaAction` e
`TransicionarPlanoAction`, que seguem intactos.

### A tela de assinatura para de escrever no banco

`AssinaturaController::garantirHistoricoFaturas()` foi removido. Ele fabricava, **durante um GET**,
uma fatura por mês desde a criação da rede, marcando meses passados como pagos com
`pago_em = vencimento + rand(0,4) dias`. Histórico fictício agora vive no `DesenvolvimentoSeeder`,
e a tela declara que a cobrança é simulada.

## Consequências

### Positivas

- A régua de planos fica monotônica: pagar só adiciona.
- O custo de operação passa a acompanhar a receita — cada unidade nova é uma licença.
- Feature flags por unidade permitem cenários reais (matriz no Pro, quiosque no Grátis).
- Some a convenção `0 = ilimitado`, e com ela seis condicionais espalhadas.
- Um `GET` deixa de escrever no banco.
- `PlanoService` (morto, sem nenhum chamador) foi removido em vez de reescrito.

### Negativas

- Refatoração ampla: 13 pontos liam `$rede->plano`. Quem escrever código novo precisa lembrar de
  usar `PlanoVigente`, nunca `$rede->plano` (que não existe mais).
- O limite de assentos é da unidade, mas a tela de usuários lista os da rede inteira — a contagem
  exibida é da unidade em contexto. Aceitável enquanto o caso comum é uma unidade só.
- A fatura da rede ainda é um valor escalar: o rateio soma as licenças e pro-rateia só a que mudou.
  Enquanto não houver `fatura_itens`, não dá para auditar de onde veio o total.

### Neutras

- `faturas.plano_id` continua existindo e aponta para o plano da última transição — perde sentido
  numa rede com licenças diferentes. Sai quando a fatura ganhar itens por unidade.
- A migration que move `plano_id` precisou descobrir o nome real da FK: `redes` nasceu como
  `contas` (`rename_contas_to_redes`) e o MySQL preserva `contas_plano_id_foreign` ao renomear a
  tabela. Assumir a convenção `redes_plano_id_foreign` quebrava a migration no meio.
