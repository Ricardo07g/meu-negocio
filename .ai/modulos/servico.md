# Modulo: Servico

CRUD de servicos oferecidos. Tipos: avulso (sessao unica) e pacote (multiplas sessoes).

## Localizacao

`app/Modules/Servico/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Models | Servico.php |
| Controllers | ServicoController.php |
| Services | ServicoService.php |
| DTOs | CriarServicoData.php, AtualizarServicoData.php |
| Requests | CriarServicoRequest.php, AtualizarServicoRequest.php |
| Policies | ServicoPolicy.php |
| Views | index, create, edit, show |
| Migrations | create_servicos_table, add_tipo, remove_qtd_sessoes_dias_semana, add_pacote_config |

## Model: Servico

- Tabela: `servicos`
- Traits: PertenceARede, SoftDeletes
- Fillable: rede_id, nome, duracao, valor, tipo, qtd_sessoes, descricao
- Casts: valor → decimal:2, tipo → TipoServico
- Relacoes: agendamentos (hasMany), vendasPacote (hasMany)
- Metodo: `isPacote()` → verifica se tipo === TipoServico::Pacote

## Tipos de servico (TipoServico enum)

| Valor | Descricao |
|-------|-----------|
| Avulso | Sessao unica — gera 1 agendamento |
| Pacote | Multiplas sessoes — gera VendaPacote com N agendamentos |

## Campos

- `nome` — nome do servico
- `duracao` — duracao em minutos (usado para calcular fim do agendamento)
- `valor` — preco em reais (decimal 10,2)
- `tipo` — avulso ou pacote
- `qtd_sessoes` — quantidade de sessoes (para pacotes)
- `descricao` — descricao opcional

## Schema: servicos

| Coluna | Tipo | Default |
|--------|------|---------|
| id | bigint PK | auto |
| rede_id | FK redes | — |
| nome | string(200) | — |
| duracao | int (minutos) | — |
| valor | decimal(10,2) | — |
| tipo | string(20) | 'avulso' |
| qtd_sessoes | int | null |
| descricao | text | null |
| deleted_at | timestamp | null |
