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
- `DesafioDoisFatoresController` — segunda etapa do login (`GET/POST login/dois-fatores`, grupo `guest`, POST com o limitador nomeado `throttle:dois-fatores` = 10/min por IP, balde proprio, em `AppServiceProvider`).
- `Support\PendenciaDoisFatores` — a pendencia do login na sessao (`registrar`, `usuario`, `registrarErro`, `descartar`): prazo de 5 min e **teto de 5 erros por pendencia** — estourou, volta ao login (e ao Turnstile). O teto e o que segura forca bruta distribuida por IPs; o throttle por IP so contem rajada. Login sem 2FA descarta pendencia de outra conta.
- **`DoisFatoresService`** (unico Service do modulo) — toda a regra do 2FA TOTP: `iniciar`, `confirmar` (devolve os codigos de recuperacao em texto puro, uma vez), `verificarCodigo` (anti-replay), `usarCodigoRecuperacao`, `regenerarCodigos`, `desativar` (recusa se a rede exige), `redefinir` (Admin), `definirExigencia` (rede), `qrCodeSvg`. Pacotes `pragmarx/google2fa` + `bacon/bacon-qr-code` — **nao** o Fortify (ADR-0022).
- Requests: `LoginRequest` (email/password), `RegistrarRequest` (nome, email unico, password `confirmed` min:8, empresa). Ambos `authorize() => true` (rotas guest).
- Views `auth::` (`login`, `registrar`, `esqueci-senha`, `redefinir-senha`, `verificar-email`, `dois-fatores`, `emails/recuperacao-senha`, `emails/verificacao-email`). Todos os controllers usam `TratamentoErros`.

## Regras de negocio / gotchas
- **Login em duas etapas** (ADR-0022): `Auth::validate` (NAO `attempt` — a senha certa nao pode autenticar quem tem 2FA). Falha -> "Credenciais inválidas."; `!ativo` -> "Sua conta está desativada.". Sem 2FA: `Auth::login` + `regenerate` + `intended(dashboard)`. Com 2FA: grava a pendencia `login.dois_fatores` (id + expira em 5 min) na sessao e manda para o desafio, **deslogado**. O desafio aceita o codigo do app OU um codigo de recuperacao e revalida `ativo` + 2FA ainda ligado (o Admin pode ter redefinido no meio).
- **2FA — gotchas**: segredo (`encrypted`) e codigos de recuperacao (sha256 em `encrypted:array`) fora do `toArray`; `two_factor_ultimo_passo` guarda o passo de 30s do ultimo codigo aceito (`verifyKeyNewer`) — o mesmo codigo nao entra duas vezes, inclusive o usado na confirmacao. Verificacao e consumo de codigo releem o usuario com `lockForUpdate` numa transacao (`comUsuarioTravado`): sem isso, duas requisicoes simultaneas decidiam com o estado velho e aceitavam o mesmo codigo. Em teste, `travel()` NAO muda o passo (o google2fa le `time()`): use a trait `tests/Concerns/DoisFatores`. **Throttle numerico do Laravel e compartilhado entre rotas**: a chave e so o usuario (logado) ou o IP (guest), sem a rota — `throttle:5,1` em duas rotas soma no mesmo balde. Para rota sensivel nova, use o prefixo (`throttle:5,1,nome-da-acao`) ou um limitador nomeado; foi o que fez configurar o 2FA com um erro de digitacao dar 429.
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
