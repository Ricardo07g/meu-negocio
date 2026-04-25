# Screenshots

Esta pasta deve conter screenshots reais do sistema rodando, capturados
apos popular o banco com o `DesenvolvimentoSeeder`. As imagens sao
linkadas a partir do `README.md` da raiz.

## Sugestoes de telas a capturar

Capturar em resolucao de **1920x1080** (ou equivalente desktop), JPG/PNG,
com o navegador em modo limpo (sem barras de extensao). Recomenda-se
salvar em PNG para nitidez de UI.

Lista minima sugerida (3 a 5 imagens):

1. **`dashboard.png`** — `/dashboard`
   Cards reais (agendamentos, clientes, receita, contas a receber, caixa).

2. **`agenda.png`** — `/agenda`
   Calendario semanal com agendamentos por atendente (Toast UI Calendar,
   cores por atendente).

3. **`venda-em-andamento.png`** — `/vendas/nova`
   Carrinho com produtos adicionados, totais, selecao de cliente, condicao
   de pagamento.

4. **`contas-a-receber.png`** — `/contas-a-receber`
   Lista de parcelas pendentes com filtros, badges de status e acoes.

5. **`caixa-diario.png`** — `/caixas?data={hoje}`
   Caixa do dia com saldo, sangrias, reforcos e baixas registradas.

## Como capturar

```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=DesenvolvimentoSeeder

# Acesse http://localhost:8080
# Login: admin@teste.com / password
```

Apos capturar, salvar nesta pasta e referenciar do `README.md`:

```markdown
![Dashboard](docs/screenshots/dashboard.png)
```
