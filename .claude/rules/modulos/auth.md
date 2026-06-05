---
paths:
  - "app/Modules/Auth/**"
---

# Modulo: Auth

Autenticacao: login, registro de nova rede, logout, recuperacao/redefinicao de senha. Guard `web`
(session). Sem Service nem DTO proprios — o registro delega ao `RedeService` do modulo Tenant.

## Entidades & status
- Nao tem model proprio. Autentica `App\Modules\Usuario\Models\Usuario` (`config/auth.php`).
- Mailable `RecuperacaoSenhaMailable` (Markdown + branding "Meu Negocio") — usado por `Usuario::sendPasswordResetNotification`.
- Tabela `password_reset_tokens` (criada na migration de `usuarios`).

## Camadas-chave
- `LoginController` — `showLoginForm`, `login`, `logout`.
- `RegistrarController` — `showRegistrationForm`, `register` (injeta `RedeService`).
- `EsqueciSenhaController` — `showLinkRequestForm`, `sendResetLinkEmail` (`Password::sendResetLink`).
- `RedefinirSenhaController` — `showResetForm`, `reset` (`Password::reset` + evento `PasswordReset`).
- Requests: `LoginRequest` (email/password), `RegistrarRequest` (nome, email unico, password `confirmed` min:8, empresa). Ambos `authorize() => true` (rotas guest).
- Views `auth::` (`login`, `registrar`, `esqueci-senha`, `redefinir-senha`, `emails/recuperacao-senha`). Todos os controllers usam `TratamentoErros`.

## Regras de negocio / gotchas
- **Login** valida `Auth::attempt`; se falha -> erro "Credenciais inválidas." Se sucesso mas `!usuario->ativo` -> `Auth::logout()` + "Sua conta está desativada." Em sucesso: `session()->regenerate()` + `redirect()->intended(dashboard)`.
- **Registro** monta toda a estrutura de tenant via `RedeService->criar(CriarRedeData, UsuarioData)`: Rede (plano free) -> Empresa padrao -> Usuario Admin -> seeds (categorias/produtos/servicos/clientes). Auto-login via `Auth::login($rede->getRelation('usuarioCriado'))` -> dashboard. NAO valida plano/empresa aqui.
- **Esqueci senha**: sempre retorna a MESMA mensagem generica ("Se o email ... estiver cadastrado ...") independente do email existir — evita vazar existencia de cadastro.
- **Rate limit**: `login`, `registrar` e `esqueci-senha` (POST) usam `throttle:5,1` (5/min).
- Rotas de login/registro/recuperacao no grupo `middleware('guest')`. `logout` (POST) sob `middleware('auth')`. Nenhuma usa `verificar.rede`/`verificar.empresa`.
- `logout` invalida E regenera o token de sessao; redireciona para `home`.

## Veja tambem
- `.claude/rules/modulos/tenant.md` / `RedeService` — estrutura criada no registro.
- `.claude/rules/multi-tenant-seguranca.md` — camadas `auth` -> `verificar.rede` -> `verificar.empresa` aplicadas DEPOIS do login.
