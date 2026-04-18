# Pendente

Funcionalidades e melhorias identificadas como pendentes. Atualizado em: 2026-04-14.

## Dashboard

- [ ] Resumo de agendamentos do dia
- [ ] Totais financeiros (receita, despesa, saldo do periodo)
- [ ] Graficos de performance
- [ ] Alertas de estoque baixo (produto.estoque_minimo)
- [ ] Proximos agendamentos

## Modulo Profissional

- [ ] Tabela `profissionais` (definida no DATABASE.md mas nao implementada)
- [ ] Model Profissional (atualmente atendente e um Usuario com `atende = true`)
- [ ] Decidir se mantém como esta ou cria modulo separado

## Relatorios

- [ ] Modulo de relatorios (bloqueado por plano `tem_relatorios`)
- [ ] Relatorio financeiro por periodo
- [ ] Relatorio de agendamentos
- [ ] Relatorio de vendas
- [ ] Relatorio de estoque

## Financeiro

- [ ] Validacao de estoque negativo na venda de produto
- [ ] Estorno automatico na venda de pacote (atualmente so avulso estorna)
- [ ] Relatorio de fluxo de caixa

## Notificacoes

- [ ] Lembrete de agendamento (email/SMS/WhatsApp)
- [ ] Alerta de estoque baixo
- [ ] Notificacao de pagamento pendente

## Cobranca/Billing

- [ ] Integracao com gateway de pagamento para cobranca de plano
- [ ] Tela de upgrade/downgrade de plano
- [ ] Fatura e historico de pagamentos da rede

## Integracao

- [ ] API REST para integracao com terceiros
- [ ] Webhook de eventos
- [ ] Exportacao de dados (CSV, PDF)

## Deploy

- [ ] Pipeline CI/CD
- [ ] Deploy DigitalOcean (Docker em producao)
- [ ] Configuracao de queue/cache/backup em producao
- [ ] Dominio e SSL

## Seguranca

- [ ] Reset de senha (tela existe no template, nao implementada)
- [ ] Verificacao de email
- [ ] 2FA
- [ ] Rate limiting

## Testes

- [ ] Testes unitarios dos Services
- [ ] Testes unitarios das Actions
- [ ] Testes de integracao dos Controllers
- [ ] Testes de Policy

## Qualidade

- [ ] Seeders completos para desenvolvimento
- [ ] Factories para testes
- [ ] Paginacao nas listagens
- [ ] Busca/filtro nas listagens
- [ ] Ordenacao nas tabelas
