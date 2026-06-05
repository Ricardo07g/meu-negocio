---
paths:
  - "app/Modules/**"
  - "app/Models/**"
  - "app/Traits/**"
  - "app/Http/Middleware/**"
  - "app/Support/**"
  - "**/Migrations/**"
---

# Multi-tenant e seguranca (rede + empresa)

Regras de isolamento. Vazar dado entre redes/empresas e o erro mais grave do projeto. Carrega ao
mexer em codigo de modulo, models, traits, middleware, support ou migrations.

## Estrategia
Single DB + colunas de tenant, em dois niveis:
- **`rede_id`** (sempre): `RedeTrait` aplica global scope via `App\Models\BaseModel`.
- **`empresa_id`** (transacional): `EmpresaTrait` aplica global scope; Admin ve todas as empresas da
  SUA rede.

## Catalogo (rede) x Transacional (empresa)
- **Catalogo — rede-level, sem `empresa_id`** (compartilhado entre empresas da rede): Cliente,
  Servico, Produto, CategoriaProduto, CategoriaDespesa.
- **Transacional — com `empresa_id`** (isolado por empresa): Agendamento, Venda, Pagamento, Despesa,
  Caixa, Estoque.

## BaseModel
`App\Models\BaseModel` (extends Model + `RedeTrait`). Todo model tenant-aware estende BaseModel.
- Excecoes (Model direto): Plano, Rede, MovimentoCaixa.
- `Usuario` e Authenticatable + traits direto, **rede-level apenas** (NAO usa EmpresaTrait — aplicar
  quebraria `auth()->user()` quando o contexto vigente difere do `usuario.empresa_id` default).
- Caixa = BaseModel + EmpresaTrait.

## Acesso do usuario as empresas
- `usuarios.empresa_id` = empresa default ao logar (preferencia). **NAO e barreira de tenancy.**
- Pivot `empresa_usuario` (`rede_id`, `empresa_id`, `usuario_id`) = fonte de verdade do conjunto de
  empresas acessiveis.
- Admin (`hasRole('Admin')`) acessa todas as empresas da rede (pivot dispensavel).
- `SalvarUsuarioRequest` exige >=1 empresa para nao-admin.
- `Usuario::atendentesDaEmpresa($empresaId)` filtra atendentes via pivot (ou Role Admin).
- `Usuario::podeAcessarEmpresa(?int)` — usado por TODAS as Policies.
- `App\Support\ContextoEmpresa::resolver()` -> id da empresa em contexto (URL > sessao com 1 empresa)
  ou null.

## Sessao / contexto corrente (modelo ME-010 v3)
- **`empresas_atuais`** (multi): universo acessivel, nao editavel manualmente. Populado pelo
  middleware `VerificarEmpresa` a cada request (Admin = todas da rede; nao-admin = pivot), podando
  ids invalidos.
- **`empresa_contexto_atual`** (single int): contexto vigente da listagem; empresa-base para criar
  registros e filtrar.
- Filtro `partials/filtro-empresa-listagem.blade.php` -> submit leva `?empresa_id=X`; middleware
  `AplicarContextoEmpresa` (alias `aplicar.contexto.empresa`) interpreta: `X` (em `empresas_atuais`)
  seta contexto; `todas` limpa; sem param respeita o existente (poda se stale).
- `EmpresaTrait` prioriza `empresa_contexto_atual` sobre `empresas_atuais` no scope e no `creating`.
  Forms criados a partir da listagem herdam a empresa do contexto silenciosamente.

## Defesa em profundidade na escrita
- Escrita transacional multi-empresa: envolva em
  `$this->comEmpresaDeCriacao($empresaId, fn () => ...)` (trait `DefineEmpresaDeCriacao`). **Nunca**
  mexa na chave de sessao `empresa_criacao_atual` na mao.
- Baixa/renegociar/cancelar parcela (Pagamento/Despesa): o controller seta
  `session('empresa_criacao_atual', $parcela->empresa_id)` no try e `forget()` no finally — garante
  `empresa_id` correto em `BaixaPagamento`/`BaixaDespesa` (NOT NULL) mesmo via link direto.
- **Caixa Diario** exige 1 empresa unica: aceita contexto (URL) OU 1 empresa no header; com varias
  sem contexto, exibe aviso pedindo escolha.

## Camadas de autorizacao (todas obrigatorias)
1. **Auth**: middleware `auth` em toda rota protegida.
2. **Rede**: middleware `verificar.rede` (`VerificarRede`) — usuario tem rede ativa? Traits
   auto-filtram por `rede_id`.
3. **Empresa**: middleware `verificar.empresa` (`VerificarEmpresa`) — Admin passa; demais precisam de
   `empresa_id` valida na mesma rede.
4. **Permissao**: `XxxPolicy` em todo model sensivel + `$this->authorize(...)` em todo metodo de
   escrita do controller (spatie/laravel-permission). A Policy precisa estar **registrada** em
   `app/Providers/AppServiceProvider.php`.
5. **Plano** (quando aplicavel): `verificar.plano:{modulo}` — feature flags (`tem_estoque`,
   `tem_financeiro`) + limites (`max_empresas`, `max_usuarios`; `0` = ilimitado).

### Permissoes (spatie/laravel-permission)
- Roles/permissions sao GLOBAIS (`teams => false`), nao tenant-scoped.
- Apenas o role **`Admin`** e seedado (com todas as permissoes); demais perfis sao criados pela UI
  (`/perfis-acesso`). O Admin e somente-leitura na UI — novas permissoes entram via seed, nao pela tela.
- Slugs no formato `recurso.acao` (`cliente.ver`, `pagamento.editar`, ...). O modulo foi renomeado de
  Papel -> PerfilAcesso, mas o slug **`papel.*`** foi mantido por compatibilidade.
- `usuarios.atende` (flag operacional de atendente) e **independente** do Role — nao e autorizacao.
- Toda Policy precisa estar registrada em `app/Providers/AppServiceProvider.php` (`$policies`).

## Isolamento — invioláveis
- Global scopes filtram `rede_id`/`empresa_id` automaticamente; auto-assign no evento `creating`.
- **Nunca** confiar em input para tenant — sempre o usuario logado/contexto.
- **Nunca** acesso cruzado entre redes. Admin ve so empresas da SUA rede.
- `withoutGlobalScope` / `DB::` cru exigem justificativa explicita; prefira o scope dos models.

## Excecoes customizadas
| Excecao | Quando |
|---|---|
| TenantNaoEncontradoException | usuario sem rede ou rede inativa |
| EmpresaNaoEncontradaException | usuario sem empresa (e nao e admin) |
| PlanoLimiteException | recurso excede limite do plano |
| ConflitoAgendamentoException | horario conflita com outro agendamento |
| NegocioException | regra de negocio generica violada |

`TratamentoErros`: NegocioException -> warning no log + msg amigavel; Validation/Authorization ->
repassa (Laravel trata, vira 422/403); outros -> error + stack trace + msg generica.
