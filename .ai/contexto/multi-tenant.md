# Multi-Tenant

## Estrategia

**Single database + tenant_id** (sem pacote externo).

Todos os registros possuem:
- `rede_id` — obrigatorio sempre
- `empresa_id` — obrigatorio quando dado pertence a uma empresa

Nao usar database/schema por cliente (preparado para futuro).

## Hierarquia

```
Rede (tenant principal)
 └── Empresa (sub-tenant)
      └── dados (usuarios, clientes, agendamentos, etc.)
```

## Traits de escopo

### PertenceARede (`app/Traits/PertenceARede.php`)
- Adiciona global scope filtrando por `rede_id` do usuario logado
- Auto-assign de `rede_id` no boot do model (creating event)
- Relacao `belongsTo(Rede)`
- Protecao contra recursao infinita na resolucao do usuario

### PertenceAEmpresa (`app/Traits/PertenceAEmpresa.php`)
- Adiciona global scope filtrando por `empresa_id` do usuario logado
- **Excecao**: usuarios com papel Admin veem dados de todas as empresas da rede
- Auto-assign de `empresa_id` no boot do model (creating event)
- Relacao `belongsTo(Empresa)`
- Mesma protecao contra recursao

## Middleware

| Middleware | Arquivo | Funcao |
|-----------|---------|--------|
| verificar.rede | `app/Http/Middleware/VerificarRede.php` | Verifica se usuario tem rede_id e se rede esta "Ativa" |
| verificar.empresa | `app/Http/Middleware/VerificarEmpresa.php` | Admin passa direto; demais precisam empresa_id valida na mesma rede |
| verificar.plano | `app/Http/Middleware/VerificarPlano.php` | Verifica feature flags do plano (estoque, financeiro, relatorios) |

## Ordem nas rotas

```
auth > verificar.rede > verificar.empresa > verificar.plano:{modulo}
```

## Regras criticas

1. **Nunca** salvar registro sem `rede_id`
2. **Nunca** salvar registro sem `empresa_id` quando aplicavel
3. **Nunca** retornar dados de outra rede
4. **Nunca** retornar dados de outra empresa (exceto Admin)
5. **Sempre** usar traits nos models
6. **Sempre** usar o usuario logado para filtrar — nunca confiar em input
7. Ao criar novo model que pertence a empresa: usar ambos traits `PertenceARede` + `PertenceAEmpresa`
8. Ao criar novo model que pertence so a rede: usar apenas `PertenceARede`
