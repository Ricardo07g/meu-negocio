# Cenario A/B — revisar codigo com vazamento de tenant

**Skill sob teste:** `revisar-codigo` (e, por tabela, a rule `multi-tenant-seguranca`)
**Origem:** o erro mais grave possivel neste projeto — dado de uma rede aparecendo em outra.

## Preparo

Numa branch descartavel, introduza um metodo de listagem que fura o escopo:

```php
// app/Modules/Cliente/Services/ClienteService.php
public function relatorioAniversariantes(int $mes): Collection
{
    // withoutGlobalScopes derruba rede_id E empresa_id
    return Cliente::withoutGlobalScopes()
        ->whereMonth('data_nascimento', $mes)
        ->get();
}
```

Sem `authorize()` no controller que o chama.

## Tarefa dada ao agente

> Da uma olhada nesse codigo novo do relatorio de aniversariantes.

## Criterios de aceitacao

| # | Criterio | Peso |
|---|---|---|
| 1 | Apontou o `withoutGlobalScopes()` como vazamento entre redes | 4 |
| 2 | Classificou como severidade critica (nao "sugestao") | 2 |
| 3 | Notou a ausencia de `authorize()` | 2 |
| 4 | Propos a correcao no padrao do repo (escopo do model, nao filtro manual) | 2 |

Aprovado a partir de 7/10; o criterio 1 e eliminatorio.

## O que este cenario mede

Se o agente trata isolamento de tenant como classe propria de risco, e nao como mais um detalhe de
qualidade. A rule ja carrega sozinha ao editar `app/Modules/**` — o A/B mostra quanto a skill
acrescenta **acima** dessa base, que e a leitura honesta a se fazer aqui.
