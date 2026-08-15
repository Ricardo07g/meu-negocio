---
paths:
  - "resources/js/**"
  - "public/js/**"
  - "**/*.blade.php"
---

# JavaScript de tela — armadilhas deste projeto

Carrega ao mexer em JS (arquivo proprio ou inline em Blade). Existe porque este e o unico codigo do
repo que **nenhuma camada da porta de qualidade enxerga**: o Pint pula Blade, o PHPStan nao le
template, e os testes Feature batem em HTTP — nao no navegador. Um handler pode nunca disparar e a
suite fica verde. Ja aconteceu duas vezes.

## SweetAlert2 do Duralux e ANTIGO — nao tem `isConfirmed`

O bundle embarcado (`public/assets/vendors/js/sweetalert2.min.js`) e anterior a v9: resolve com
**`{value: true}`**. Ler so `result.isConfirmed` da `undefined`, o `if` nunca entra, e o modal fecha
como se nada fosse — **sem erro no console**, que e o que torna a falha invisivel em revisao.

```js
// ERRADO — abre o modal e o botao nao faz nada
Swal.fire({...}).then(r => { if (r.isConfirmed) form.submit(); });

// CERTO — padrao do repo
Swal.fire({...}).then(r => { if (r.value) form.submit(); });

// CERTO — quando o codigo precisa sobreviver a uma futura atualizacao do bundle
Swal.fire({...}).then(r => { if (r && (r.value === true || r.isConfirmed === true)) form.submit(); });
```

Com `preConfirm`, o que importa e ter `res.value` (o retorno do `preConfirm`), nao um booleano —
veja o handler de renegociacao em `layouts/app.blade.php`.

O teste `tests/Feature/BladeLintTest.php` barra o PR que reintroduzir isso, e o hook
`.claude/hooks/blade-lint.sh` avisa na hora da edicao.

## Interpolar Blade em JS: `@json`, nunca aspas simples

```blade
{{-- ERRADO — vira &quot; na tela e quebra o script inteiro se o texto tiver apostrofo --}}
<script>Swal.fire({ text: '{{ session('sucesso') }}' });</script>

{{-- CERTO --}}
<script>Swal.fire({ text: @json(session('sucesso')) });</script>
```

Vale para qualquer **texto livre**: mensagem de flash, nome digitado pelo cliente, observacao.
`'{{ route('clientes.buscar') }}'` continua liberado — URL de rota nao tem aspas nem apostrofo.

## Submit programatico

`form.submit()` **nao dispara** o evento `submit`, entao pula todo listener — inclusive o
`data-confirm` do layout e o loading de botao do `mn-admin.js`. Quando quiser passar por eles, use
`form.requestSubmit()`. Quando quiser justamente pular (o SweetAlert ja confirmou), `submit()` e o
certo — e o padrao dos forms ocultos da assinatura.

## Conteudo injetado por AJAX perde os handlers

Handlers ligados no `DOMContentLoaded` so alcancam o que existia no load. Telas com polling (extrato
de conta, exportacoes) precisam **re-ligar** apos injetar linhas — veja `bindConfirm()` em
`Conta/Views/extrato.blade.php` — ou usar delegacao de evento no `document`, como faz o
`public/js/mn-admin.js`.

## Onde cada coisa mora

- **`public/js/mn-admin.js`** — comportamento global do admin (loading de submit). Fora do Vite,
  servido por `asset()` com `?v=filemtime` nos dois layouts. Mexeu aqui, afeta o sistema inteiro.
- **`resources/js/**`** — entries do Vite (calendario da agenda, recorte de imagem do produto).
- **Inline em Blade** — comportamento de uma tela so. E o caso mais comum e o mais arriscado: nao
  passa por lint nem por build.

## Verificacao — a unica que vale

Teste HTTP nao prova que um botao funciona. Depois de mexer em JS de tela, **clique de verdade**:

```
node .claude/skills/validar-implementacao/scripts/smoke.cjs "/minha-assinatura" ".btn-renovar-teste"
```

O smoke confere status, erros de console e a presenca do seletor. Para provar o **efeito** do clique
(o form submeteu? o flash apareceu?), dirija o navegador e leia o resultado — foi assim que os dois
bugs de SweetAlert apareceram, depois de passarem por 339 testes verdes.

## Veja tambem

- `.claude/rules/ui-duralux.md` — padroes visuais, icones Feather, componentes.
- `tests/Feature/BladeLintTest.php` — as regras acima como teste.
