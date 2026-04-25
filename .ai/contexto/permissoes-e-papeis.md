# Permissoes e Papeis

## Sistema

Usa `spatie/laravel-permission` ^7.2 com guard `web`.

## Modelo

**Apenas o perfil Admin master e seedado** com todas as permissoes do
catalogo. Demais perfis (ex: Recepcao, Financeiro, Estoque) sao criados
pelo Admin via UI (`/perfis-acesso`) conforme a necessidade do negocio.

Validacao de papel em formularios e dinamica via `exists:roles,name`
(nao ha mais enum hardcoded). Isso significa que:

- O sistema nao impoe uma matriz fixa de papeis.
- Cada rede pode ter seus proprios papeis customizados.
- Permission slugs (`recurso.acao`) sao fixos no codigo (ver catalogo
  abaixo), papeis sao dados.

Trade-off: a UI nao oferece um "wizard de papeis padrao" ao registrar
uma rede nova — o Admin precisa criar manualmente os perfis adicionais.
Para portfolio, este e o comportamento desejado (mantem o seeder simples
e demonstra o modulo PerfilAcesso em uso real).

## Permissoes (catalogo, formato `recurso.acao`)

### Rede
- rede.ver, rede.editar, rede.configurar, rede.cobranca

### Empresa
- empresa.ver, empresa.criar, empresa.editar, empresa.excluir

### Usuario
- usuario.ver, usuario.criar, usuario.editar, usuario.excluir

### Cliente
- cliente.ver, cliente.criar, cliente.editar, cliente.excluir

### Servico
- servico.ver, servico.criar, servico.editar, servico.excluir

### Agendamento
- agendamento.ver, agendamento.criar, agendamento.editar, agendamento.cancelar, agendamento.excluir

### Financeiro
- financeiro.ver, financeiro.criar, financeiro.editar, financeiro.excluir, financeiro.relatorio

### Pagamento
- pagamento.ver, pagamento.criar, pagamento.editar, pagamento.excluir

### Despesa
- despesa.ver, despesa.criar, despesa.editar, despesa.excluir

### Categoria de Despesa
- categoria_despesa.ver, categoria_despesa.criar, categoria_despesa.editar, categoria_despesa.excluir

### Estoque
- estoque.ver, estoque.criar, estoque.editar, estoque.excluir

### Produto
- produto.ver, produto.criar, produto.editar, produto.excluir

### Movimento de estoque
- movimento_estoque.ver, movimento_estoque.criar

### Perfil de Acesso
Slug mantido como `papel.*` por compatibilidade com codigo existente
(modulo foi renomeado para PerfilAcesso, mas as permissions no banco
seguem usando o slug `papel`).
- papel.ver, papel.criar, papel.editar, papel.excluir

### Plano
- plano.ver, plano.alterar

## Funcao operacional vs autorizacao

A coluna `usuarios.atende` (boolean) e independente do Role. Indica que
o usuario realiza atendimentos e deve aparecer no select de atendente
da Agenda — nao tem nada a ver com permissoes.

Exemplo: um usuario pode ser Admin (autorizacao total) e tambem atender
clientes (`atende = true`). Outro pode ser Recepcao (sem permissao para
agendar) mas tambem ter `atende = false` (nao aparece na agenda).

## Policies

Cada Model do dominio tem uma Policy registrada explicitamente em
`AppServiceProvider::$policies` (auto-discovery do Laravel nao alcanca
`App\Modules\{X}\Policies`). Policies existentes:

- Tenant: RedePolicy, EmpresaPolicy, PlanoPolicy
- Usuario: UsuarioPolicy
- Cliente: ClientePolicy
- Servico: ServicoPolicy
- Agenda: AgendamentoPolicy
- Pagamento: PagamentoPolicy
- Despesa: DespesaPolicy, CategoriaDespesaPolicy
- Produto: ProdutoPolicy, CategoriaProdutoPolicy
- Estoque: MovimentoEstoquePolicy
- Caixa: CaixaPolicy
- Venda: VendaPacotePolicy
- PerfilAcesso: PerfilAcessoPolicy (registrada para Spatie\Role)

## Validacao

Toda action que muta estado deve validar: permissao + tenant + empresa + plano.
Nunca executar sem validar.
