<?php

declare(strict_types=1);

namespace App\Modules\Conta\Exports;

use App\Enums\{FormatoExportacao, TipoLancamento};
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

    public function __construct(private FormatoExportacao $formato) {}

    /**
     * Escreve a query de lançamentos (já filtrada por conta/período/tenant) no
     * arquivo local informado. Retorna o total de linhas de dados escritas.
     *
     * @param  Builder<Lancamento>  $query
     */
    public function escrever(Builder $query, string $caminhoLocal): int
    {
        $writer = $this->criarWriter();
        $writer->openToFile($caminhoLocal);
        $writer->addRow(Row::fromValues(self::CABECALHO));

        $linhas = 0;
        $query->reorder()->orderBy('data')->orderBy('id')
            ->chunk(1000, function ($lancamentos) use ($writer, &$linhas): void {
                foreach ($lancamentos as $lancamento) {
                    $writer->addRow(Row::fromValues($this->linha($lancamento)));
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
}
