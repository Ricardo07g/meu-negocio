# Regras de Seguranca

## Autenticacao

- Guard `web` com sessao
- Model: `App\Modules\Usuario\Models\Usuario`
- Campo `ativo` controla acesso (false = login bloqueado)
- Sessao regenerada no login e logout

## Autorizacao (4 camadas)

### 1. Auth (login)
- Middleware `auth` em todas rotas protegidas
- Sem login, sem acesso

### 2. Tenant (rede)
- Middleware `verificar.rede`
- Verifica: usuario tem rede_id? rede esta ativa?
- Traits auto-filtram por rede_id

### 3. Empresa
- Middleware `verificar.empresa`
- Admin passa direto (ve todas empresas da rede)
- Demais precisam empresa_id valida na mesma rede
- Traits auto-filtram por empresa_id

### 4. Permissao
- Policies em todos models
- `$this->authorize()` em todos metodos do controller
- Permissoes via spatie/laravel-permission

## Plano
- Middleware `verificar.plano:{modulo}`
- Feature flags: tem_estoque, tem_financeiro, tem_relatorios
- Limites: max_empresas, max_usuarios

## Isolamento de dados

- Global scopes filtram por rede_id e empresa_id automaticamente
- Auto-assign no creating event do model
- Nunca confiar em input para tenant — sempre usar usuario logado
- Nunca permitir acesso cruzado entre redes
- Admin ve todas empresas da SUA rede (nunca de outra)

## Error handling

- Trait `TratamentoErros` nos controllers
- NegocioException → warning no log, mensagem amigavel
- ValidationException → passa direto (Laravel trata)
- AuthorizationException → passa direto (Laravel trata)
- Outros → error no log com stack trace, mensagem generica

## Excecoes customizadas

| Excecao | Quando |
|---------|--------|
| TenantNaoEncontradoException | Usuario sem rede ou rede inativa |
| EmpresaNaoEncontradaException | Usuario sem empresa (e nao e admin) |
| PlanoLimiteException | Recurso excede limite do plano |
| ConflitoAgendamentoException | Horario conflita com outro agendamento |
| NegocioException | Regra de negocio generica violada |

## Protecoes no frontend

- `@can` directives no menu (oculta itens sem permissao)
- Verificacao de plano no menu (oculta financeiro/estoque se plano nao permite)
- SweetAlert2 para confirmacoes antes de acoes destrutivas (data-confirm)

## Pontos de atencao (melhorias futuras)

- Reset de senha nao implementado
- Verificacao de email nao implementada
- 2FA nao implementado
- Rate limiting nao configurado
- CSRF ativo (padrao Laravel)
