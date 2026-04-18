# Modulo: Auth

Login e registro de usuarios. Sem Service ou DTO proprio — usa RedeService para registro.

## Localizacao

`app/Modules/Auth/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Controllers | LoginController.php, RegistrarController.php |
| Requests | LoginRequest.php, RegistrarRequest.php |
| Views | login.blade.php, registrar.blade.php |

## LoginController

### showLoginForm()
Retorna view de login.

### login()
1. Valida email/password via LoginRequest
2. Tenta autenticar com Auth::attempt
3. Verifica se usuario esta ativo (`ativo = true`)
4. Regenera sessao
5. Redireciona para dashboard

### logout()
1. Auth::logout
2. Invalida e regenera sessao

## RegistrarController

### showRegistrationForm()
Retorna view de registro.

### register()
1. Valida dados via RegistrarRequest
2. Chama `RedeService->criar()` com CriarRedeData + CriarUsuarioData
3. Auto-login do usuario criado
4. Redireciona para dashboard

## Fluxo de registro

O registro cria toda a estrutura de tenant:
Rede (plano free) → Empresa padrao → Usuario admin → Categorias padrao

Ver: [fluxos/onboarding.md](../fluxos/onboarding.md)

## Observacoes

- Usa trait TratamentoErros nos controllers
- Guard: `web` (session)
- Model de auth: `App\Modules\Usuario\Models\Usuario`
- Configurado em `config/auth.php`
