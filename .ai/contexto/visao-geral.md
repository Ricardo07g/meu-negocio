# Visao Geral

## O que e

**Meu Negocio** e um SaaS multi-tenant para pequenos negocios e autonomos.
Publico-alvo: clinicas, saloes de beleza, massoterapia, fisioterapia, prestadores de servico.

## Objetivo

Oferecer um sistema completo de gestao com:
- Agendamento de servicos (avulso e pacote)
- Gestao financeira (pagamentos, despesas, caixa)
- Controle de estoque
- Cadastro de clientes, produtos, servicos
- Controle de acesso por papeis e permissoes
- Multi-empresa (rede de negocios)

## Modelo de negocio

- SaaS com planos (free, basic, pro, business)
- Cada plano limita funcionalidades e quantidade de recursos
- Preparado para cobranca futura (ainda nao implementada)
- Preparado para open source

## Hierarquia organizacional

```
Plano
 └── Rede (organizacao/conta)
      └── Empresa (unidade de negocio)
           ├── Usuarios (funcionarios)
           ├── Clientes
           ├── Servicos
           ├── Agendamentos
           ├── Vendas (pacote, avulso, produto)
           ├── Pagamentos
           ├── Despesas
           ├── Produtos
           ├── Movimentos de estoque
           └── Caixa
```

## Conceitos-chave

- **Rede**: organizacao principal (antigamente chamada "Conta"). Tem um plano associado.
- **Empresa**: unidade dentro da rede. Uma rede pode ter varias empresas.
- **Usuario**: pertence a uma empresa e tem um papel (Admin, Gerente, etc).
- **Admin da rede**: pode ver dados de todas as empresas da rede.
- **Usuario comum**: so ve dados da propria empresa.
