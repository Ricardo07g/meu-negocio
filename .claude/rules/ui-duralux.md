---
paths:
  - "app/Modules/**/Views/**"
  - "resources/views/**"
  - "**/*.blade.php"
---

# UI — padrao Duralux Admin

Carrega ao mexer em Blade/Views. **Sempre** busque o padrao visual no template Duralux (comercial;
mantenha uma copia local para referencia — ver `NOTICE.md`) antes de criar UI. Base: Bootstrap 5,
icones Feather, modais SweetAlert2.

## Icones Feather (gotcha importante)
O tema usa Feather como **fonte CSS**: use a classe `feather-nome` (ex.: `<i class="feather-edit-3">`).
**Nao** use `data-feather="..."` — nao renderiza neste tema.

## Padroes consolidados
- **Form CRUD**: `_form.blade.php` partial compartilhado (create/edit) com
  `@php $entidade = $entidade ?? null; @endphp`.
- **Botoes de form**: componente `<x-form-botoes>` (Voltar/Salvar, min-width 300px).
- **Botao Voltar (show)**: `btn btn-light px-5 py-2` com `min-width: 300px`.
- **Busca de entidades**: AJAX via `initAjaxSearch()` (global no layout) — **nunca** `<select>`
  carregando tudo. Endpoints: `GET clientes/buscar?q=`, `produtos/buscar?q=`, `servicos/buscar?q=`.
- **Tabelas**: `table table-hover` ou `table table-striped table-hover`.
- **Badges de status**: `bg-success` (ativo/pago), `bg-warning` (pendente), `bg-danger` (cancelado),
  `bg-secondary` (estornado).
- **Modais SweetAlert**: inputs `width:100%;max-width:100%;box-sizing:border-box;`, textareas
  `rows="3"`, cor primaria `#3454d1`.
- **Ações por permissao**: gates `@can(...)` ocultam itens de menu/dropdown; checar plano para
  ocultar financeiro/estoque quando o plano nao permite.
- **Confirmacao destrutiva**: SweetAlert2 via `data-confirm`.
- **Layout admin** usa `asset()` (assets pre-compilados do Duralux em `public/assets/`), **nao** Vite;
  a landing publica e telas novas com Vite usam entries dedicados.
