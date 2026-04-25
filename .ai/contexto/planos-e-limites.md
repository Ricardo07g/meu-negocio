# Planos e Limites

## Tabela de planos

| Plano | max_empresas | max_usuarios | tem_estoque | tem_financeiro |
|-------|-------------|-------------|-------------|----------------|
| free | 1 | 2 | false | false |
| basic | 2 | 5 | true | true |
| pro | 5 | 10 | true | true |
| business | 0 (ilimitado) | 0 (ilimitado) | true | true |

Nota: `0` em max_empresas/max_usuarios = ilimitado.

## Como funciona a validacao

### ValidarPlanoAction (`app/Modules/Tenant/Actions/ValidarPlanoAction.php`)

Recebe: `Rede` + nome do recurso.

Para recursos contaveis (empresa, usuario):
- Se max = 0 → ilimitado, passa
- Se count atual >= max → lanca `PlanoLimiteException`

Para feature flags (estoque, financeiro):
- Se flag = false → lanca `PlanoLimiteException`

### Middleware verificar.plano

Rota: `verificar.plano:{recurso}` (ex: `verificar.plano:financeiro`)
Verifica flags booleanas do plano: `tem_estoque`, `tem_financeiro`.

### Onde a validacao acontece

| Acao | Validacao |
|------|-----------|
| Criar empresa | CriarEmpresaAction chama ValidarPlanoAction('empresa') |
| Criar usuario | CriarUsuarioAction chama ValidarPlanoAction('usuario') |
| Acessar financeiro | Middleware verificar.plano:financeiro |
| Acessar estoque | Middleware verificar.plano:estoque |

## Plano da rede

O plano e associado a `Rede`, nao a `Empresa`.
Todas as empresas da mesma rede compartilham o mesmo plano.

Relacao: `Rede.plano_id → Plano.id`

## Futuro

- Cobranca ainda nao implementada
- Sistema preparado para integrar gateway de pagamento
- Modulo de Relatorios documentado como roadmap no README (fora de escopo do portfolio)
