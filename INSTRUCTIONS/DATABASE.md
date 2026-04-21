# =====================================================

# DATABASE.md

# Estrutura oficial do banco

# Projeto: Meu Negócio

# =====================================================

Claude deve seguir exatamente esta estrutura.

Nunca criar tabelas fora deste padrão sem perguntar.

Todos registros devem suportar multi-tenant.

Todos registros devem ter:

rede_id
empresa_id (quando aplicável)

---

# REDES

Tabela: redes

id
nome
plano_id
status
created_at
updated_at

Rede possui muitas empresas.

---

# PLANOS

Tabela: planos

id
nome
max_empresas
max_usuarios
tem_estoque
tem_financeiro
tem_relatorios
created_at
updated_at

Plano pertence a muitas redes.

---

# EMPRESAS

Tabela: empresas

id
rede_id
nome
documento
telefone
email
created_at
updated_at

Empresa pertence a rede.

---

# USUARIOS

Tabela: usuarios

id
rede_id
empresa_id
nome
email
password
ativo
created_at
updated_at

Usuario pertence a empresa.

---

# PAPEIS

spatie roles

Tabela: roles

Tabela: permissions

Tabela: model_has_roles

Tabela: role_has_permissions

---

# CLIENTES

Tabela: clientes

id
rede_id
empresa_id
nome
telefone
email
observacoes
created_at
updated_at

Cliente pertence a empresa.

---

# SERVICOS

Tabela: servicos

id
rede_id
empresa_id
nome
duracao
valor
tipo
created_at
updated_at

tipo: avulso, pacote

Servico pertence a empresa.

---

# PROFISSIONAIS

Tabela: profissionais

id
rede_id
empresa_id
usuario_id
created_at
updated_at

Profissional é usuario.

---

# AGENDAMENTOS

Tabela: agendamentos

id
rede_id
empresa_id
cliente_id
servico_id
profissional_id
venda_pacote_id (nullable)
inicio
fim
status
created_at
updated_at

Status:

agendado
confirmado
cancelado
finalizado

---

# VENDAS PACOTE

Tabela: vendas_pacote

id
rede_id
empresa_id
cliente_id
servico_id
profissional_id
valor_total
qtd_sessoes
status
created_at
updated_at

status: ativo, concluido, cancelado

VendaPacote pertence a cliente, servico, profissional.
VendaPacote possui muitos agendamentos.

---

# PAGAMENTOS

Tabela: pagamentos

id
rede_id
empresa_id
agendamento_id
valor
forma_pagamento
status
created_at
updated_at

formas:

pix
dinheiro
cartao

Observacao: forma_pagamento e nullable. Null indica venda a prazo (fiado).
A forma real e registrada em baixas_pagamento quando o cliente paga.

---

# DESPESAS

Tabela: despesas

id
rede_id
empresa_id
nome
valor
data
created_at
updated_at

---

# PRODUTOS

Tabela: produtos

id
rede_id
empresa_id
nome
quantidade
valor
created_at
updated_at

---

# MOVIMENTOS ESTOQUE

Tabela: movimentos_estoque

id
rede_id
empresa_id
produto_id
tipo
quantidade
created_at
updated_at

tipo:

entrada
saida
ajuste

---

# REGRAS IMPORTANTES

Todos registros devem validar:

rede_id
empresa_id

Nunca permitir acesso cruzado.

Sempre usar policies.

Sempre usar permissions.

Sempre validar plano.

Nunca criar tabela sem perguntar.
