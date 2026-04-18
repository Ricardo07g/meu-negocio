# .ai/ — Contexto do Projeto para IA

Cardapio de arquivos para contextualizar assistentes de IA sobre o projeto **Meu Negocio**.
Cada arquivo e autonomo — leia apenas os que forem relevantes para a tarefa.

## Estrutura

### contexto/ — O QUE e o projeto
| Arquivo | Descricao |
|---------|-----------|
| [visao-geral.md](contexto/visao-geral.md) | Objetivo, publico-alvo, modelo de negocio |
| [stack.md](contexto/stack.md) | Stack tecnica completa e dependencias |
| [multi-tenant.md](contexto/multi-tenant.md) | Estrategia tenant, traits, scopes, regras |
| [permissoes-e-papeis.md](contexto/permissoes-e-papeis.md) | Papeis, permissoes, matriz completa |
| [planos-e-limites.md](contexto/planos-e-limites.md) | Planos, limites, validacao |

### modulos/ — CADA modulo isolado
| Arquivo | Modulo |
|---------|--------|
| [tenant.md](modulos/tenant.md) | Rede + Empresa + Plano |
| [auth.md](modulos/auth.md) | Login e registro |
| [usuario.md](modulos/usuario.md) | CRUD de usuarios |
| [cliente.md](modulos/cliente.md) | CRUD de clientes |
| [servico.md](modulos/servico.md) | Servicos avulso e pacote |
| [agenda.md](modulos/agenda.md) | Agendamentos + calendario |
| [venda.md](modulos/venda.md) | Vendas (pacote, avulso, produto) |
| [pagamento.md](modulos/pagamento.md) | Pagamentos e baixas |
| [caixa.md](modulos/caixa.md) | Caixa: abertura, fechamento, sangria, reforco |
| [despesa.md](modulos/despesa.md) | Despesas |
| [produto.md](modulos/produto.md) | Produtos + categorias |
| [estoque.md](modulos/estoque.md) | Movimentos de estoque |
| [papel.md](modulos/papel.md) | Gestao de papeis |
| [dashboard.md](modulos/dashboard.md) | Painel principal |

### fluxos/ — COMO as coisas funcionam ponta-a-ponta
| Arquivo | Fluxo |
|---------|-------|
| [onboarding.md](fluxos/onboarding.md) | Registro > Rede > Empresa > 1o usuario |
| [venda-avulso.md](fluxos/venda-avulso.md) | Agendar servico avulso > finalizar > pagar |
| [venda-pacote.md](fluxos/venda-pacote.md) | Vender pacote > agendar sessoes > controlar |
| [venda-produto.md](fluxos/venda-produto.md) | Vender produto > baixa estoque > pagar |
| [ciclo-agendamento.md](fluxos/ciclo-agendamento.md) | Agendado > confirmado > finalizado/cancelado |
| [ciclo-pagamento.md](fluxos/ciclo-pagamento.md) | Pendente > pago/cancelado/estornado |
| [ciclo-caixa.md](fluxos/ciclo-caixa.md) | Abrir > movimentar > fechar |
| [movimentacao-estoque.md](fluxos/movimentacao-estoque.md) | Entrada/saida/ajuste |

### guias/ — COMO desenvolver seguindo o padrao
| Arquivo | Guia |
|---------|------|
| [criar-modulo.md](guias/criar-modulo.md) | Passo-a-passo novo modulo |
| [criar-crud.md](guias/criar-crud.md) | Template CRUD completo |
| [criar-migration.md](guias/criar-migration.md) | Convencoes de migration |
| [adicionar-permissao.md](guias/adicionar-permissao.md) | Como adicionar permissao/papel |

### progresso/ — ONDE estamos
| Arquivo | Conteudo |
|---------|----------|
| [implementado.md](progresso/implementado.md) | O que esta pronto |
| [pendente.md](progresso/pendente.md) | O que falta |
| [decisoes.md](progresso/decisoes.md) | Decisoes arquiteturais tomadas |

### regras/ — LIMITES e convencoes
| Arquivo | Conteudo |
|---------|----------|
| [codigo.md](regras/codigo.md) | Convencoes de codigo |
| [banco-de-dados.md](regras/banco-de-dados.md) | Regras de banco |
| [seguranca.md](regras/seguranca.md) | Regras de seguranca |
