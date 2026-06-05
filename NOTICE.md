# NOTICE — Licenças de terceiros

O código-fonte da **aplicação** (diretórios `app/`, `database/`, `routes/`, `config/`, `tests/`,
`resources/` exceto assets de terceiros) está licenciado sob **MIT** — ver [`LICENSE`](LICENSE).

Os arquivos abaixo, distribuídos no repositório, **não** são cobertos pela licença MIT do projeto e
permanecem sob as licenças de seus respectivos autores.

## Template de UI

- **Duralux Admin** (também identificado como *NEXEL — CRM Admin Dashboard*) — template
  administrativo **comercial** (Bootstrap 5). Os assets compilados e os fontes `.scss` em
  `public/assets/` derivam desse template e são © de seus autores, utilizados sob licença adquirida.
  Eles **não** são redistribuíveis sob a licença MIT deste projeto.

## Bibliotecas de terceiros empacotadas em `public/assets/vendors/`

Bundles minificados de bibliotecas open source, cada uma sob a sua própria licença (a maioria MIT):

- **Bootstrap** — MIT — https://getbootstrap.com
- **SweetAlert2** — MIT — https://sweetalert2.github.io
- **Select2** — MIT — https://select2.org
- **Toast UI Calendar** (`@toast-ui/calendar`) — MIT — https://ui.toast.com/tui-calendar
- **Tagify** — MIT — https://github.com/yairEO/tagify
- **Quill** — BSD-3-Clause — https://quilljs.com
- **Feather Icons** (fonte de ícones) — MIT — https://feathericons.com

> A lista acima cobre as bibliotecas principais; arquivos adicionais em `public/assets/vendors/`
> seguem as licenças declaradas por seus respectivos projetos. Consulte cada projeto para os termos
> completos.

## Dependências gerenciadas

As dependências de backend (Composer / `vendor/`) e de frontend (npm / `node_modules/`) **não** são
versionadas; suas licenças constam em `composer.json` / `composer.lock` e `package.json`.
