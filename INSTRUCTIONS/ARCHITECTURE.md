# =====================================================

# ARCHITECTURE.md

# Arquitetura do Projeto

# Projeto: Meu Negócio

# =====================================================

Este projeto deve seguir uma arquitetura organizada.

Objetivo:

* código limpo
* código modular
* fácil manutenção
* fácil open source
* fácil testes
* fácil evolução

Nunca gerar código fora deste padrão.

---

# Arquitetura base

Usar MVC do Laravel.

Controller
Model
View

Mas com camadas extras.

Service
Repository
DTO
Request
Policy
Action
Support
Helper

---

# Estrutura de pastas

app/

Models/
Http/
Controllers/
Requests/
Policies/

Services/
Repositories/
Actions/
Support/
Helpers/

DTO/

Enums/

---

# Regras gerais

Controller não pode ter regra de negócio.

Controller apenas:

validar request
chamar service
retornar response

Model não pode ter regra complexa.

Service contém regra de negócio.

Repository contém acesso ao banco.

Action contém ação específica.

Support contém classes utilitárias.

Helper contém funções auxiliares.

DTO contém dados validados.

---

# Controllers

Responsável por:

request
response

Nunca acessar DB direto.

Nunca escrever regra de negócio.

Sempre usar Service.

---

# Requests

Cada endpoint deve ter Request.

Validar dados.

Nunca validar no controller.

---

# Services

Responsável por:

regra de negócio
fluxo
validações extras

Pode usar:

Actions
Repositories
DTO
Support

Pode ter subpastas.

Exemplo:

Services/Cliente/
Services/Agendamento/

---

# Repositories

Responsável por:

query
save
update
delete

Não colocar regra de negócio.

Pode usar Eloquent.

Criar apenas quando necessário.

Não criar abstração desnecessária.

Laravel já tem ORM.

Usar repository somente quando fizer sentido.

---

# Actions

Usar para ações específicas.

Exemplo:

CriarAgendamentoAction
CancelarAgendamentoAction
RegistrarPagamentoAction

Action deve fazer uma coisa.

Action pode ser usada dentro do Service.

---

# Support

Classes auxiliares.

Exemplo:

CalendarSupport
FinanceiroSupport
PlanoSupport

Não acessar DB direto.

Apenas lógica.

---

# Helpers

Funções globais.

Usar pouco.

Somente utilidades simples.

---

# DTO

Usar spatie/laravel-data.

DTO para:

entrada
saida
service

Nunca passar array solto.

---

# Policies

Todos models devem ter policy.

Nunca validar permissão no controller.

Sempre usar policy.

---

# Repository pattern

Usar somente quando necessário.

Laravel já abstrai DB.

Não criar interface sem necessidade.

Criar repository quando:

query complexa
reuso
multi-tenant complexo
performance

Caso contrário usar model.

---

# Services obrigatórios

ContaService
EmpresaService
UsuarioService
ClienteService
ServicoService
AgendamentoService
PagamentoService
DespesaService
ProdutoService
PlanoService

---

# Actions obrigatórias

CriarAgendamentoAction
CancelarAgendamentoAction
FinalizarAgendamentoAction

RegistrarPagamentoAction

CriarClienteAction
AtualizarClienteAction

CriarEmpresaAction

CriarUsuarioAction

ValidarPlanoAction

---

# Regras importantes

Nunca colocar lógica no controller.

Nunca colocar regra no repository.

Nunca colocar regra complexa no model.

Service controla fluxo.

Action executa ação.

Repository acessa DB.

DTO transporta dados.

Policy valida acesso.

---

# Modularização

Cada módulo deve ter:

Controller
Service
Action
DTO
Request
Policy

Exemplo:

Cliente
Servico
Agendamento
Financeiro
Estoque
Conta
Empresa

---

# Open source

Código deve ser:

legível
organizado
modular
sem overengineering
sem bagunça

---

# Regra final

Sempre seguir ARCHITECTURE.md
Nunca gerar código fora deste padrão.
