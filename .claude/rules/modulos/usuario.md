---
paths:
  - "app/Modules/Usuario/**"
---

# Modulo: Usuario

CRUD de usuarios da rede (admin) + "Meu Perfil" self-service. Usuario e a entidade autenticavel
(`config/auth.php` -> `App\Modules\Usuario\Models\Usuario`, guard `web`).

## Entidades & status
- Model `Usuario` (tabela `usuarios`). Estende `Authenticatable` (NAO BaseModel).
  Traits: `HasRoles` (Spatie), `Notifiable`, `RedeTrait`, `SoftDeletes`.
- **Rede-level apenas**: usa `RedeTrait` (global scope `rede_id`), **NAO** usa `EmpresaTrait`.
  Aplicar EmpresaTrait quebraria `auth()->user()` quando o contexto difere do `empresa_id` default.
- Fillable: `rede_id, empresa_id, nome, email, password, ativo, atende`.
  Casts: `email_verified_at => datetime`, `password => hashed`, `ativo => boolean`, `atende => boolean`. Hidden: `password, remember_token`.
- `ativo` (bool, default true): se false, login bloqueado. `atende` (bool, default false): aparece como atendente em Agenda/Venda.

## Camadas-chave
- `UsuarioController` — CRUD admin; `index/create/store/edit/update/destroy` (NAO ha `show` — sempre edicao). `authorize(...)` em cada metodo.
- `PerfilController` — self-service "Meu Perfil": `index`, `atualizar` (nome/email), `atualizarSenha`.
- `UsuarioService` — `listar` (paginate 20), `buscar`, `criar` (delega Action), `atualizar`, `excluir` (soft delete).
- `CriarUsuarioAction` — valida plano (`ValidarPlanoAction->executar($rede, 'usuario')`), cria, `assignRole`, sincroniza pivot.
- `UsuarioData` (DTO unico criar/editar): `nome, email, ?password, ?empresa_id, ?papel, ?ativo, ?atende, ?empresas[]`.
- `SalvarUsuarioRequest` (`isMethod('post')` decide criar/editar). `AtualizarPerfilRequest`, `AtualizarSenhaPerfilRequest` (self-service).
- `UsuarioPolicy` — `viewAny/create` por permissao `usuario.*`; `update` exige mesma `rede_id` +
  alcance (Admin alcanca toda a rede; nao-admin so quem compartilha empresa); `delete` exige mesma `rede_id`.
  **Nao autorize por `$alvo->empresa_id`**: e preferencia de login, costuma ser nulo (a tela nem tem
  o campo) e `podeAcessarEmpresa(null)` devolve false ANTES de checar o papel — era o 403 que batia
  ate no Admin.

## Regras de negocio / gotchas
- **Pivot `empresa_usuario`** (`rede_id, empresa_id, usuario_id`) e a fonte de verdade do conjunto de empresas
  acessiveis (relacao `empresas()`). `empresa_id` na tabela e so a empresa default ao logar (preferencia, NAO barreira de tenancy).
  O form **nao tem** campo de empresa default — `CriarUsuarioAction`/`UsuarioService` preenchem com a
  primeira empresa do pivot (antes nascia nulo, e o usuario ficava sem unidade para abrir no login).
- `SalvarUsuarioRequest`: nao-admin exige `empresas` array com `min:1`; Admin nao precisa de pivot (acessa tudo). `empresas.*` validados como pertencentes a propria `rede_id`.
- Ao sincronizar pivot (Action e Service): o `sync` precisa preencher `rede_id` no pivot — `mapWithKeys(fn($id) => [(int)$id => ['rede_id' => $usuario->rede_id]])`.
- `CriarUsuarioAction`: `atende` default = `($papel === 'Admin')` quando nao informado; `ativo` sempre true na criacao.
- `Service::atualizar`: password so atualiza se informado; `syncRoles([papel])` so se papel presente; `empresas` so sincroniza se `!== null`.
- `scopeAtendentesDaEmpresa($empresaId)` — `atende=true` E (pivot contem a empresa OU Role `Admin`). Usado por Agenda/Venda.
- `podeAcessarEmpresa(?int)`: null => false; Admin => true; senao checa pivot. Usado por TODAS as Policies.
- Migration original cria `conta_id` (FK `contas`); renomeado para `rede_id`/`redes` por `Tenant/.../2026_03_22_300001_rename_contas_to_redes`. Cria tambem `password_reset_tokens` e `sessions`.
- `sendPasswordResetNotification` sobrescrito para enviar `RecuperacaoSenhaMailable` (branding) em vez da notification default.
- **`MustVerifyEmail`** (ADR-0022): `email_verified_at` fora do `$fillable` (use `markEmailAsVerified()`/`forceFill`). `sendEmailVerificationNotification` envia `VerificacaoEmailMailable`. `UsuarioService::criar` dispara o link (a Action NAO — ela tambem serve registro e seeders). `revalidarEmailSeAlterado()` depois do `update` (Service e `PerfilController`) zera a verificacao e reenvia quando o e-mail muda; o Perfil entao redireciona para `verificacao.aviso`.
- **2FA (ADR-0022)**: colunas `two_factor_secret` (`encrypted`), `two_factor_recovery_codes` (`encrypted:array`), `two_factor_confirmed_at`, `two_factor_ultimo_passo` — todas fora do `$fillable` (o `DoisFatoresService` usa `forceFill`); segredo e codigos em `$hidden`. `temDoisFatoresAtivo()` = segredo + confirmado. `DoisFatoresController` (deste modulo): aba **Seguranca** do Meu Perfil (`perfil.dois-fatores.*`, partial `_dois-fatores`, abre por `?aba=seguranca`; **iniciar**, regenerar e desativar exigem `senha_2fa` via `SenhaDoisFatoresRequest`, que escolhe o **error bag pela rota** (`iniciar`/`codigos`/`desativar`) — os forms ficam lado a lado e o erro aparece no form certo (`@error('senha_2fa', 'desativar')`); cada um com throttle de prefixo proprio) + acoes do Admin `rede.exigir-2fa` (PATCH, `RedePolicy::configurarSeguranca`) e `usuarios.redefinir-2fa` (`UsuarioPolicy::redefinirDoisFatores` = Admin, mesma rede, nunca a si mesmo).
- `TratamentoErros` nunca devolve segredo no `withInput()` (`camposQueNaoVoltam`: senha, senha_atual, senha_2fa, codigo...).
- Rotas: `Route::resource('usuarios')->except(['show'])` sob `auth + verificar.rede + verificar.empresa`. **A URI de edicao e `usuarios/{id}/editar`** (resource localizado), nao `/edit`. Perfil: `GET/POST perfil` + `POST perfil/senha` (fora de `verificar.empresa`).

## Veja tambem
- `.claude/rules/multi-tenant-seguranca.md` — RedeTrait, pivot `empresa_usuario`, `podeAcessarEmpresa`, camadas de autorizacao, Policies.
- `.claude/rules/modulos/perfilacesso.md` — papeis/permissoes Spatie (slug `papel.*`).
