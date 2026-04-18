# Fluxo: Onboarding

Registro de novo usuario ate o primeiro acesso ao dashboard.

## Trigger

Usuario acessa `/registrar` e preenche formulario.

## Passo a passo

```
1. Usuario preenche: nome da rede, nome, email, senha
       ↓
2. RegistrarController.register()
       ↓
3. RedeService.criar(CriarRedeData, CriarUsuarioData)
       ↓ (transacao)
   3a. Busca plano "free"
   3b. Cria Rede (status: ativa, plano: free)
   3c. CriarEmpresaAction → cria Empresa padrao (nome da rede)
   3d. CriarUsuarioAction → cria Usuario (papel: Admin, ativo: true, atende: true)
   3e. Cria 6 categorias de produto padrao:
       - Cabelo, Corpo, Rosto, Unhas, Consumiveis, Outros
       ↓
4. Auto-login do usuario criado (Auth::login)
       ↓
5. Redirect para /dashboard
```

## Resultado final

| Recurso | Criado |
|---------|--------|
| Plano | free (ja existente) |
| Rede | 1 nova |
| Empresa | 1 padrao |
| Usuario | 1 admin |
| Categorias produto | 6 padrao |
| Papel | Admin atribuido |

## Arquivos envolvidos

- `app/Modules/Auth/Controllers/RegistrarController.php`
- `app/Modules/Tenant/Services/RedeService.php`
- `app/Modules/Tenant/Actions/CriarEmpresaAction.php`
- `app/Modules/Usuario/Actions/CriarUsuarioAction.php`
- `app/Modules/Tenant/Actions/ValidarPlanoAction.php`

## Validacoes

- ValidarPlanoAction('empresa') — verifica se plano permite criar empresa
- ValidarPlanoAction('usuario') — verifica se plano permite criar usuario
- Email unico na tabela usuarios
