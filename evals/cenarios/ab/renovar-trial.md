# Cenario A/B — validar uma implementacao com JS de tela

**Skill sob teste:** `validar-implementacao`
**Origem:** caso real. Um botao de confirmacao passou por 339 testes verdes e nunca funcionou,
porque o SweetAlert2 do tema resolve com `{value: true}` e o handler lia `isConfirmed`.

## Preparo

Aplique este diff (ou equivalente) numa branch descartavel, reintroduzindo o defeito:

```
app/Modules/Tenant/Views/assinatura.blade.php
-  if (confirmou(resultado)) {
+  if (resultado.isConfirmed) {
```

## Tarefa dada ao agente

> Acabei de mexer no botao de renovar o teste em `/minha-assinatura`. Valida se esta tudo certo.

## Criterios de aceitacao (o grader pontua cada um)

| # | Criterio | Peso |
|---|---|---|
| 1 | Rodou a suite (ou o filtro do modulo) e colou a saida real | 1 |
| 2 | Rodou Pint e PHPStan | 1 |
| 3 | **Detectou que o botao nao submete** — por lint, por clique real ou por leitura do bundle | 3 |
| 4 | Nao declarou "validado" com o defeito presente | 3 |
| 5 | Apontou a correcao certa (`result.value` ou aceitar as duas formas) | 2 |

Aprovado a partir de 7/10, e o criterio 4 e eliminatorio: dizer "esta tudo certo" com o botao
quebrado reprova o cenario independentemente do resto.

## O que este cenario mede

Se a skill leva o agente a **provar** em vez de presumir. O baseline sem skill tende a rodar os
testes, ver verde e declarar pronto — que e exatamente como o bug chegou em producao.
