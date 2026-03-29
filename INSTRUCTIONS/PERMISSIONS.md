# =====================================================

# PERMISSIONS.md

# Sistema de Permissões e Papéis

# Projeto: Meu Negócio

# =====================================================

O sistema deve usar:

spatie/laravel-permission

Nunca criar permissões fora deste padrão.

Todas as ações devem validar permissão.

Todas as rotas devem validar permissão.

Todos os models devem ter policy.

---

# Estrutura

Rede
└── Empresa
└── Usuario
└── Papel
└── Permissões

Usuario pertence a empresa.

Usuario possui papel.

Papel possui permissões.

---

# Papéis padrão

Dono
Admin
Gerente
Profissional
Recepcao
Financeiro
Estoque
Visualizador

Nunca remover estes papéis.

Pode adicionar novos.

---

# Permissões padrão

## Rede

rede.ver
rede.editar
rede.configurar
rede.cobranca

## Empresa

empresa.ver
empresa.criar
empresa.editar
empresa.excluir

## Usuario

usuario.ver
usuario.criar
usuario.editar
usuario.excluir

## Cliente

cliente.ver
cliente.criar
cliente.editar
cliente.excluir

## Servico

servico.ver
servico.criar
servico.editar
servico.excluir

## Profissional

profissional.ver
profissional.criar
profissional.editar
profissional.excluir

## Agendamento

agendamento.ver
agendamento.criar
agendamento.editar
agendamento.cancelar
agendamento.excluir

## Financeiro

financeiro.ver
financeiro.criar
financeiro.editar
financeiro.excluir
financeiro.relatorio

## Pagamento

pagamento.ver
pagamento.criar
pagamento.editar
pagamento.excluir

## Despesa

despesa.ver
despesa.criar
despesa.editar
despesa.excluir

## Estoque

estoque.ver
estoque.criar
estoque.editar
estoque.excluir

## Produto

produto.ver
produto.criar
produto.editar
produto.excluir

## Movimento estoque

movimento_estoque.ver
movimento_estoque.criar

## Plano

plano.ver
plano.alterar

---

# Permissões por papel

## Dono

todas permissões

## Admin

quase todas

não pode alterar plano

## Gerente

clientes
servicos
agenda
financeiro

## Profissional

agenda.ver
agenda.criar
cliente.ver
servico.ver

## Recepcao

cliente.*
agendamento.*
pagamento.*

## Financeiro

financeiro.*
pagamento.*
despesa.*

## Estoque

produto.*
movimento_estoque.*

## Visualizador

somente ver

---

# Permissões por plano

Plano free

1 empresa
2 usuarios
sem estoque
sem relatorio

Plano basic

2 empresas
5 usuarios
estoque sim

Plano pro

5 empresas
10 usuarios
financeiro completo

Plano business

ilimitado

Sempre validar plano.

---

# Regras obrigatórias

Toda action deve validar:

permite?
tenant?
empresa?
plano?

Nunca executar sem validar.

---

# Policies obrigatórias

RedePolicy
EmpresaPolicy
UsuarioPolicy
ClientePolicy
ServicoPolicy
AgendamentoPolicy
PagamentoPolicy
DespesaPolicy
ProdutoPolicy
PlanoPolicy

---

# Middleware obrigatório

VerificarPermissao
VerificarPlano
VerificarTenant
VerificarEmpresa

Usar em todas rotas.

---

# Regra final

Nunca criar acesso sem permissão.
Nunca ignorar plano.
Nunca ignorar tenant.
Nunca ignorar empresa.
