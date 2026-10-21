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
- Mailable `VerificacaoEmailMailable` (mesmo molde) — usado por `Usuario::sendEmailVerificationNotification` (link assinado, 60 min).
- Tabela `password_reset_tokens` (criada na migration de `usuarios`).

## Camadas-chave
- `LoginController` — `showLoginForm`, `login`, `logout`.
- `RegistrarController` — `showRegistrationForm`, `register` (injeta `RedeService`).
- `EsqueciSenhaController` — `showLinkRequestForm`, `sendResetLinkEmail` (`Password::sendResetLink`).
- `RedefinirSenhaController` — `showResetForm`, `reset` (`Password::reset` + evento `PasswordReset`).
- `VerificacaoEmailController` — `aviso`, `verificar` (`EmailVerificationRequest::fulfill`), `reenviar`. Rotas `verificacao.*` sob `auth` (sem `verified`).
- Requests: `LoginRequest` (email/password), `RegistrarRequest` (nome, email unico, password `confirmed` min:8, empresa). Ambos `authorize() => true` (rotas guest).
- Views `auth::` (`login`, `registrar`, `esqueci-senha`, `redefinir-senha`, `emails/recuperacao-senha`). Todos os controllers usam `TratamentoErros`.

## Regras de negocio / gotchas
- **Login** valida `Auth::attempt`; se falha -> erro "Credenciais inválidas." Se sucesso mas `!usuario->ativo` -> `Auth::logout()` + "Sua conta está desativada." Em sucesso: `session()->regenerate()` + `redirect()->intended(dashboard)`.
- **Registro** monta toda a estrutura de tenant via `RedeService->criar(CriarRedeData, UsuarioData)`: Rede -> Empresa padrao (Pro em trial) -> Usuario Admin -> seed enxuto. Auto-login + `session()->regenerate()`, envia o link de verificacao **fora da transacao** e vai para `verificacao.aviso`. NAO valida plano/empresa aqui.
- **Verificacao de e-mail (ADR-0022)**: `verified:verificacao.aviso` no grupo autenticado inteiro — sem confirmar, so a tela de aviso, reenvio (`throttle:6,1`), link (`signed` + `throttle:6,1`) e logout. O `{hash}` e `sha1` do e-mail ATUAL: link de endereco antigo nao vale. Falha de envio nunca derruba o fluxo (`Usuario::enviarVerificacaoSemFalhar` loga e segue). Usuarios existentes foram marcados verificados na migration; seeders/factory/`CriaTenant` criam verificados (`UsuarioFactory::naoVerificado()` para o contrario).
- **Esqueci senha**: sempre retorna a MESMA mensagem generica ("Se o email ... estiver cadastrado ...") independente do email existir — evita vazar existencia de cadastro. **Falha no envio tambem cai na mensagem generica**: o controller captura a excecao do `Password::sendResetLink`, loga e responde igual. Toda falha de transporte acontece para quem EXISTE (endereco desconhecido devolve `INVALID_USER` sem tentar enviar), entao deixar a excecao subir viraria oraculo de cadastro — ADR-0020.
- **Envio real**: producao usa a API HTTPS do Resend (`MAIL_MAILER=resend` + `RESEND_API_KEY`), nunca SMTP — o Railway descarta conexao SMTP em silencio. Dev usa `log`; testes usam `array`. Ver ADR-0020.
- **Rate limit**: `login`, `registrar` e `esqueci-senha` (POST) usam `throttle:5,1` (5/min).
- Rotas de login/registro/recuperacao no grupo `middleware('guest')`. `logout` (POST) sob `middleware('auth')`. Nenhuma usa `verificar.rede`/`verificar.empresa`.
- `logout` invalida E regenera o token de sessao; redireciona para `home`.

## Veja tambem
- `.claude/rules/modulos/tenant.md` / `RedeService` — estrutura criada no registro.
- `.claude/rules/multi-tenant-seguranca.md` — camadas `auth` -> `verificar.rede` -> `verificar.empresa` aplicadas DEPOIS do login.
