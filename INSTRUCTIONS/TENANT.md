# =====================================================

# TENANT.md

# Regras de Multi-Tenant

# Projeto: Meu Negócio

# =====================================================

Este projeto é um SaaS multiempresa.

Claude deve seguir exatamente estas regras.

Nunca criar código multi-tenant sem seguir este arquivo.

Nunca permitir acesso entre redes.

Nunca permitir acesso entre empresas.

Sempre validar tenant.

---

# Estratégia multi-tenant

Usar estratégia:

single database + tenant_id

Todos registros devem possuir:

rede_id
empresa_id (quando aplicável)

Não usar database por cliente por enquanto.

Não usar schema por cliente por enquanto.

Sistema deve ser preparado para mudar no futuro.

---

# Estrutura do sistema

Rede
└── Empresas
└── Usuarios
└── Clientes
└── Servicos
└── Profissionais
└── Agendamentos
└── Pagamentos
└── Despesas
└── Produtos
└── MovimentosEstoque

---

# Regras obrigatórias

Todo model deve ter:

rede_id

Quando for dado da empresa:

empresa_id

Nunca salvar registro sem rede_id.

Nunca salvar registro sem empresa_id quando necessário.

---

# Escopo automático

Todos queries devem filtrar por:

rede_id

Quando for empresa:

empresa_id

Exemplo correto:

where rede_id = usuario.rede_id

Exemplo correto:

where empresa_id = usuario.empresa_id

Nunca retornar dados de outra rede.

Nunca retornar dados de outra empresa.

---

# Usuario logado

Usuario deve ter:

rede_id
empresa_id
papel

Sempre usar usuario logado para filtrar.

Nunca confiar em input.

---

# Admin da rede

Admin da rede pode ver:

todas empresas da rede

Mas não pode ver outra rede.

---

# Usuario da empresa

Usuario normal só pode ver:

dados da propria empresa

---

# Validação de plano

Antes de criar:

empresa
usuario
produto
servico

validar limite do plano.

Plano define:

max_empresas
max_usuarios
tem_estoque
tem_financeiro

Nunca ignorar plano.

---

# Policies obrigatórias

Todos models devem ter policy.

RedePolicy
EmpresaPolicy
ClientePolicy
ServicoPolicy
AgendamentoPolicy
PagamentoPolicy
ProdutoPolicy

Sempre validar:

view
create
update
delete

---

# Middleware obrigatório

Criar middleware:

VerificarRede
VerificarEmpresa
VerificarPlano

Usar em todas rotas.

---

# Segurança

Nunca permitir:

acesso sem login
acesso sem rede
acesso sem empresa
acesso sem permissão

Sempre validar:

Auth
Tenant
Permissao
Plano

---

# Futuro

Sistema deve permitir no futuro:

database por cliente
subdomain tenant
schema tenant

Mas não implementar agora.

---

# Regra final

Nunca criar código multi-tenant sem seguir TENANT.md
