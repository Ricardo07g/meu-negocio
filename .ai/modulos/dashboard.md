# Modulo: Dashboard

Painel principal. Implementacao minima — apenas controller + view.

## Localizacao

`app/Modules/Dashboard/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Controllers | DashboardController.php |
| Views | dashboard.blade.php |

## DashboardController

Metodo unico `index()` que retorna a view do dashboard.
Usa trait TratamentoErros.

## Estado

Modulo basico. Futuras melhorias possiveis:
- Resumo de agendamentos do dia
- Totais financeiros (receita, despesa, saldo)
- Graficos de performance
- Alertas de estoque baixo
- Proximos agendamentos
