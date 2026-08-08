<?php

declare(strict_types=1);

namespace App\Modules\Conta\Exports;

use App\Enums\{FormatoExportacao, TipoLancamento};
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Conta\Models\Lancamento;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\{Options as CsvOptions, Writer as CsvWriter};
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Escreve o extrato (planilha) de uma conta num arquivo local, em streaming e
 * por chunks — memória baixa mesmo em períodos grandes (o motivo do job, ADR-0012).
 * CSV usa `;` + BOM (Excel pt-BR abre em colunas e com acentos); XLSX grava o
 * valor como número real (célula somável).
 */
class EscritorExtrato
{
    /** @var list<string> */
    private const CABECALHO = ['Data', 'Tipo', 'Categoria', 'Descrição', 'Forma', 'Valor'];

    /** @var list<string> */
    private const CABECALHO_RECEBIMENTOS = ['Data', 'Forma', 'Cliente', 'Valor', 'Estornado'];

    public function __construct(private FormatoExportacao $formato) {}

    /**
     * Escreve a query de lançamentos (já filtrada por conta/período/tenant) no
     * arquivo local informado. Retorna o total de linhas de dados escritas.
     *
     * @param  Builder<Lancamento>  $query
     */
    public function escrever(Builder $query, string $caminhoLocal): int
    {
        return $this->escreverEmChunks(
            $query,
            $caminhoLocal,
            self::CABECALHO,
            fn (Lancamento $lancamento): array => $this->linha($lancamento),
        );
    }

    /**
     * Variante para conta banco/carteira, cujo extrato NÃO é o razão: essas contas
     * não acumulam `Lancamento` (cartão/pix caem só como Baixa — ADR-0011), então
     * exportar lançamentos devolvia uma planilha só com o cabeçalho enquanto a tela
     * listava os recebimentos. Aqui o arquivo espelha a tela.
     *
     * @param  Builder<BaixaPagamento>  $query
     */
    public function escreverRecebimentos(Builder $query, string $caminhoLocal): int
    {
        return $this->escreverEmChunks(
            $query,
            $caminhoLocal,
            self::CABECALHO_RECEBIMENTOS,
            fn (BaixaPagamento $baixa): array => $this->linhaRecebimento($baixa),
        );
    }

    /**
     * Streaming por chunks de 1000 — memória baixa mesmo em períodos grandes
     * (o motivo do job, ADR-0012). Só o cabeçalho e o mapeamento da linha
     * mudam entre o razão e os recebimentos.
     *
     * @param  Builder<Lancamento>|Builder<BaixaPagamento>  $query
     * @param  list<string>  $cabecalho
     * @param  callable(mixed): list<string|float>  $mapear
     */
    private function escreverEmChunks(Builder $query, string $caminhoLocal, array $cabecalho, callable $mapear): int
    {
        $writer = $this->criarWriter();
        $writer->openToFile($caminhoLocal);
        $writer->addRow(Row::fromValues($cabecalho));

        $linhas = 0;
        $query->reorder()->orderBy('data')->orderBy('id')
            ->chunk(1000, function ($registros) use ($writer, &$linhas, $mapear): void {
                foreach ($registros as $registro) {
                    $writer->addRow(Row::fromValues($mapear($registro)));
                    $linhas++;
                }
            });

        $writer->close();

        return $linhas;
    }

    private function criarWriter(): CsvWriter|XlsxWriter
    {
        return match ($this->formato) {
            FormatoExportacao::Csv => new CsvWriter(new CsvOptions(';', '"', true)),
            FormatoExportacao::Xlsx => new XlsxWriter,
        };
    }

    /** @return list<string|float> */
    private function linha(Lancamento $lancamento): array
    {
        $credito = $lancamento->tipo === TipoLancamento::Credito;
        $valor = (float) $lancamento->valor;

        return [
            $lancamento->data->format('d/m/Y'),
            $credito ? 'Entrada' : 'Saída',
            ucfirst((string) $lancamento->categoria),
            (string) $lancamento->descricao,
            $lancamento->forma_pagamento_nome ?? '—',
            // XLSX: número real (somável no Excel). CSV: string pt-BR (vírgula decimal).
            $this->formato === FormatoExportacao::Xlsx
                ? ($credito ? $valor : -$valor)
                : ($credito ? '' : '-').number_format($valor, 2, ',', '.'),
        ];
    }

    /**
     * Linha de recebimento, no mesmo recorte da tela: valor BRUTO da baixa mais
     * uma coluna dizendo se foi estornada — quem soma decide se desconta. O
     * cliente vem nullsafe porque cancelar a venda apaga o título (SoftDeletes);
     * a query usa `comOrigem()` justamente para não perder esse nome.
     *
     * @return list<string|float>
     */
    private function linhaRecebimento(BaixaPagamento $baixa): array
    {
        $valor = $baixa->valorTotal();

        return [
            $baixa->data->format('d/m/Y H:i'),
            $baixa->forma_pagamento_nome ?? '—',
            // `??` tem semântica de isset: a cadeia sobrevive a cliente nulo
            // (venda de balcão) sem nullsafe — mesmo idioma de VendaService:290.
            $baixa->parcela->pagamento->cliente->nome ?? '—',
            $this->formato === FormatoExportacao::Xlsx
                ? $valor
                : number_format($valor, 2, ',', '.'),
            $baixa->estornado_em !== null ? 'Sim' : '—',
        ];
    }
}
