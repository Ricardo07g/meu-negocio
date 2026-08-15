---
paths:
  - "app/Modules/**"
  - "tests/**"
---

# Testes por feature — a definição de pronto

Carrega ao mexer em qualquer módulo ou teste. Responde a uma pergunta que a skill `gerar-teste-model`
não responde, porque ela só entra quando alguém pede testes: **quais testes esta feature precisa ter
antes de virar PR?**

A porta de qualidade confere que a suíte está **verde** — o que é fácil quando não se escreveu nada.
Verde não é cobertura. Esta rule existe para tornar a exigência automática em vez de lembrada.

## A régua

Para cada característica da mudança, os testes correspondentes são **obrigatórios**. Não é
checklist a preencher: é o mínimo que torna a feature revisável.

| Se a feature… | então precisa de | modelo a copiar |
|---|---|---|
| grava/lê dado **tenant-aware** | isolamento: rede/empresa A não alcança dado de B | `tests/Feature/MultiTenant/IsolamentoTest.php` |
| expõe **rota mutável** (POST/PUT/PATCH/DELETE) | caminho autorizado **e** 403 sem permissão | `tests/Feature/Pagamento/PermissoesTest.php` |
| mexe em **dinheiro** (título, parcela, baixa, caixa) | o caso feliz **e** o estorno/cancelamento | `tests/Feature/Caixa/EstornoTest.php` |
| depende de **plano/licença** | com a flag ligada **e** desligada | `tests/Feature/Tenant/LicencaPorEmpresaTest.php` |
| tem **regra de recusa** (`NegocioException`) | um teste por recusa, não só o caminho feliz | `tests/Feature/Tenant/AssinaturaTest.php` |
| renderiza **tela** | 200 + o dado-chave presente | qualquer `*/index.blade` coberto |
| tem **JS de tela** | clique real no navegador — teste HTTP não pega handler que não dispara | ver abaixo |
| roda em **fila ou agendador** | o job/comando executado de fato, não só enfileirado | `tests/Feature/AgendadorHttpTest.php` |

### Onde a régua veio de sangue

**Isolamento e 403** são os dois mais esquecidos e os dois mais graves. Vazar dado entre redes é o
pior erro possível neste projeto, e hoje o isolamento está coberto em uma fração dos contextos —
toda feature nova deve melhorar essa conta, nunca piorar.

**Recusas antes do caminho feliz.** Na renovação de teste gratuito, as três recusas (nunca testou,
teste vigente, licença paga) valiam mais que o caso de sucesso: é nelas que mora a regra de negócio.
Um teste que só prova que o sucesso funciona não prova que a regra existe.

**JS de tela** é a lição mais cara desta base. Dois botões — a troca de plano e a exclusão de
exportação — passaram por centenas de testes verdes e **nunca funcionaram**, porque o defeito estava
no handler do navegador. Teste HTTP prova que a rota responde, não que alguém consegue chegar nela.

## O caso do JS, em concreto

Três camadas, da mais barata para a mais cara. As duas primeiras são obrigatórias; a terceira, quando
o comportamento é o ponto da feature.

1. **Lint** — `tests/Feature/BladeLintTest.php` roda sozinho e barra as armadilhas conhecidas
   (SweetAlert sem fallback, interpolação em aspas simples, diretivas desbalanceadas).
2. **Teste de view** — a tela responde 200 e mostra o elemento-chave (`assertSee` no rótulo do botão).
3. **Clique real** — dirigir o navegador e verificar o **efeito**, não só a presença:

```bash
node .claude/skills/validar-implementacao/scripts/smoke.cjs "/minha-assinatura" ".btn-renovar-teste"
```

Para provar o efeito (o form submeteu? o flash apareceu?), veja `.claude/rules/javascript-telas.md`.

## O que NÃO precisa de teste

Nem toda mudança pede cobertura, e inflar a suíte tem custo real de manutenção:

- ajuste de texto, rótulo ou CSS sem lógica;
- refactor puro já coberto por testes existentes — o teste que não muda é justamente a prova;
- documentação, ADR, comentário;
- getter trivial que só devolve uma propriedade.

Na dúvida, o critério é: **se isso quebrar em produção, eu descobriria por quê?** Se a resposta é não,
falta teste.

## Nomes

Em português, descrevendo o **comportamento**, não o método: `test_renovar_e_rejeitado_em_licenca_paga`,
não `test_renovar_trial_action`. O nome do teste é a primeira linha do relatório de falha — ele
precisa dizer o que se perdeu, não onde o código mora.

## Veja também

- Skill `gerar-teste-model` — **como** escrever (`CriaTenant`, factories sem `HasFactory`, SQLite).
- Skill `validar-implementacao` — provar que funciona antes de dizer que está pronto.
- `.claude/rules/javascript-telas.md` — armadilhas do JS e como validar por clique.
- `.claude/rules/multi-tenant-seguranca.md` — o que exatamente precisa de isolamento.
