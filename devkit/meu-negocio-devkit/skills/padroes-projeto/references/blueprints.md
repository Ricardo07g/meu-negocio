# Blueprints — arquivos canonicos a copiar

Para cada artefato, abra o arquivo indicado e espelhe o estilo. Preferimos apontar para codigo real
(que evolui com o projeto) a colar trechos que envelhecem. O modulo **Produto** e o exemplo mais
completo e atual de um CRUD de catalogo.

## Tabela rapida

| Vou escrever...        | Copie o estilo de...                                             |
|------------------------|------------------------------------------------------------------|
| Controller (CRUD)      | `app/Modules/Produto/Controllers/ProdutoController.php`          |
| Service                | `app/Modules/Produto/Services/ProdutoService.php`                |
| Action (escrita complexa) | `app/Modules/Venda/Actions/VenderEtapasAction.php`            |
| Request unificado      | `app/Modules/Produto/Requests/SalvarProdutoRequest.php`          |
| DTO (spatie-data)      | `app/Modules/Produto/DTOs/ProdutoData.php`                       |
| Policy                 | `app/Modules/Produto/Policies/ProdutoPolicy.php`                 |
| Model (catalogo)       | `app/Modules/Produto/Models/Produto.php` / `Cliente/Models/Cliente.php` |
| Model (transacional)   | qualquer model que use `EmpresaTrait` (ex.: Caixa, Pagamento)    |
| View `_form` partial   | `app/Modules/Produto/Views/_form.blade.php`                      |
| Migration              | migrations recentes em `app/Modules/<Modulo>/Migrations/`        |
| Factory                | `database/factories/UsuarioFactory.php` (+ Rede/Empresa)         |
| Teste Feature          | `tests/Feature/Venda/*`, `tests/Feature/Pagamento/PermissoesTest.php` |

## Pontos de atencao por artefato

- **Controller**: confira que cada acao mutavel chama `$this->authorize(...)`. Index/show/store/update/
  destroy delegam ao Service. Use `XxxData::from($request)` para montar o DTO.
- **Request**: `authorize()` resolve permissao (com `routeIs()` se a Request for compartilhada);
  `rules()` muda conforme `isMethod('post')` (criar) vs. update. Regras de unicidade ignoram o proprio id no update.
- **DTO**: propriedades tipadas; use `Optional` quando o campo pode faltar; nunca exponha `rede_id`/
  `empresa_id` vindos do request (sao resolvidos pelos traits/sessao).
- **Model**: `protected $fillable` sem `rede_id`/`empresa_id` (preenchidos pelos traits no `creating`);
  `casts(): array`; relacoes nas secoes ASCII. Veja como `Fatura`/`Produto` organizam.
- **Policy**: metodos `viewAny/view/create/update/delete`, checando permissao Spatie e, em transacional,
  `Usuario::podeAcessarEmpresa($model->empresa_id)`. **Registrar** em `AppServiceProvider::$policies`.
- **Migration**: `up()` cria; `down()` desfaz exatamente. FKs: `cascadeOnDelete()` / `nullOnDelete()` /
  `restrictOnDelete()` conforme a relacao (ver `docs/ADR`).
- **Rota**: registrar em `routes/web.php` no grupo do modulo, nome em portugues.

## Checklist mental antes de entregar codigo

1. Multi-tenant: o dado respeita `rede_id` (BaseModel) e, se transacional, `empresa_id` (EmpresaTrait)?
2. Controller fino + Service/Action? Request e DTO unificados?
3. Policy criada e **registrada**?
4. View usa `_form` partial + `<x-form-botoes>` + AJAX search?
5. Migration tem `down()`?
6. Pint limpo: `docker exec meu-negocio-app vendor/bin/pint <arquivos>`.
