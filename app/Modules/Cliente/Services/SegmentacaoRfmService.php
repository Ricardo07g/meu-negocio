<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Services;

use App\Enums\{SegmentoRfm, StatusVendaEtapas, StatusVendaProduto};
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Segmentacao RFM da carteira: Recencia, Frequencia e Valor por cliente.
 *
 * **Nao chama IA e nao depende dela.** Esta e a inteligencia do recurso; a analise por
 * IA e um enriquecimento opcional por cima. A tela que consome isto continua util com o
 * provedor desligado.
 *
 * Tenancy assimetrica (nao e descuido): `Cliente` e rede-level (sem `empresa_id`) enquanto
 * as vendas sao por empresa. As agregacoes saem de VendaProduto/VendaEtapas — logo, ja
 * filtradas pelo `EmpresaTrait` — e os clientes vem pela rede, mesma assimetria que o
 * `DashboardService` documenta em `totalClientes()`.
 */
class SegmentacaoRfmService
{
    /**
     * Faixas fixas em vez de quintis: com base pequena, quintil oscila a cada venda.
     *
     * Pares [corte, nota] e nao mapa `corte => nota` de proposito: chave de array em PHP
     * nao aceita float — `[0.8 => 3, 0.4 => 2]` vira `[0 => 3, 0 => 2]` e a segunda apaga a
     * primeira, silenciosamente. As faixas de valor sao fracionarias, entao o mapa mentiria.
     *
     * @var array<int, array{0: float|int, 1: int}>
     */
    private const FAIXAS_RECENCIA = [[30, 5], [60, 4], [90, 3], [180, 2]];

    /** @var array<int, array{0: float|int, 1: int}> */
    private const FAIXAS_FREQUENCIA = [[10, 5], [6, 4], [3, 3], [2, 2]];

    /**
     * Valor relativo a media da propria base: uma clinica e um salao tem escalas diferentes.
     *
     * @var array<int, array{0: float|int, 1: int}>
     */
    private const FAIXAS_VALOR = [[2.0, 5], [1.2, 4], [0.8, 3], [0.4, 2]];

    /**
     * @return array{
     *     periodo_meses: int,
     *     total_clientes: int,
     *     clientes_com_compra: int,
     *     clientes_sem_compra: int,
     *     receita_total: float,
     *     ticket_medio: float,
     *     segmentos: array<int, array{chave: string, label: string, cor: string, descricao: string, clientes: int, percentual: float, receita: float, ticket_medio: float}>,
     *     clientes: Collection<int, array<string, mixed>>
     * }
     */
    public function segmentar(int $meses = 12): array
    {
        $desde = CarbonImmutable::now()->subMonths($meses)->startOfDay();

        $compras = $this->agregarCompras($desde);
        $totalClientes = Cliente::query()->count();

        $receitaTotal = (float) $compras->sum('valor');
        $mediaPorCliente = $compras->isEmpty() ? 0.0 : $receitaTotal / $compras->count();

        $clientes = $this->classificar($compras, $mediaPorCliente);

        return [
            'periodo_meses' => $meses,
            'total_clientes' => $totalClientes,
            'clientes_com_compra' => $clientes->count(),
            'clientes_sem_compra' => max(0, $totalClientes - $clientes->count()),
            'receita_total' => round($receitaTotal, 2),
            'ticket_medio' => $clientes->isEmpty() ? 0.0 : round($receitaTotal / $clientes->count(), 2),
            'segmentos' => $this->resumirSegmentos($clientes),
            'clientes' => $clientes,
        ];
    }

    // ██████╗ ██████╗ ███╗   ██╗███████╗██╗   ██╗██╗  ████████╗ █████╗
    // ██╔════╝██╔═══██╗████╗  ██║██╔════╝██║   ██║██║  ╚══██╔══╝██╔══██╗
    // ██║     ██║   ██║██╔██╗ ██║███████╗██║   ██║██║     ██║   ███████║
    // ██║     ██║   ██║██║╚██╗██║╚════██║██║   ██║██║     ██║   ██╔══██║
    // ╚██████╗╚██████╔╝██║ ╚████║███████║╚██████╔╝███████╗██║   ██║  ██║
    //  ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝ ╚═════╝ ╚══════╝╚═╝   ╚═╝  ╚═╝

    /**
     * Duas fontes de venda somadas por cliente. Vendas canceladas ficam de fora — cliente
     * nao vira campeao por uma compra que foi desfeita.
     *
     * @return Collection<int, array{cliente_id: int, compras: int, valor: float, ultima: CarbonImmutable}>
     */
    private function agregarCompras(CarbonImmutable $desde): Collection
    {
        $porCliente = [];

        $fontes = [
            VendaProduto::query()
                ->where('status', StatusVendaProduto::Ativa->value)
                ->whereNotNull('cliente_id'),
            VendaEtapas::query()
                ->whereIn('status', [StatusVendaEtapas::Ativo->value, StatusVendaEtapas::Concluido->value]),
        ];

        foreach ($fontes as $fonte) {
            $linhas = $fonte
                ->where('data', '>=', $desde->toDateString())
                ->groupBy('cliente_id')
                ->selectRaw('cliente_id, COUNT(*) as compras, SUM(valor_total) as valor, MAX(data) as ultima')
                ->get();

            foreach ($linhas as $linha) {
                $id = (int) $linha->getAttribute('cliente_id');
                $ultima = CarbonImmutable::parse((string) $linha->getAttribute('ultima'));

                if (! isset($porCliente[$id])) {
                    $porCliente[$id] = ['cliente_id' => $id, 'compras' => 0, 'valor' => 0.0, 'ultima' => $ultima];
                }

                $porCliente[$id]['compras'] += (int) $linha->getAttribute('compras');
                $porCliente[$id]['valor'] += (float) $linha->getAttribute('valor');
                $porCliente[$id]['ultima'] = $ultima->greaterThan($porCliente[$id]['ultima'])
                    ? $ultima
                    : $porCliente[$id]['ultima'];
            }
        }

        return collect(array_values($porCliente));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function classificar(Collection $compras, float $mediaPorCliente): Collection
    {
        if ($compras->isEmpty()) {
            return collect();
        }

        $nomes = Cliente::query()
            ->whereIn('id', $compras->pluck('cliente_id')->all())
            ->pluck('nome', 'id');

        $hoje = CarbonImmutable::now()->startOfDay();

        return $compras
            ->map(function (array $linha) use ($nomes, $mediaPorCliente, $hoje): array {
                $dias = (int) $linha['ultima']->startOfDay()->diffInDays($hoje);

                $r = $this->pontuar($dias, self::FAIXAS_RECENCIA, menorEhMelhor: true);
                $f = $this->pontuar($linha['compras'], self::FAIXAS_FREQUENCIA);
                $razao = $mediaPorCliente > 0 ? $linha['valor'] / $mediaPorCliente : 0.0;
                $m = $this->pontuar($razao, self::FAIXAS_VALOR);

                /** @var array<string, mixed> $classificado */
                $classificado = [
                    'cliente_id' => $linha['cliente_id'],
                    'nome' => $nomes[$linha['cliente_id']] ?? 'Cliente removido',
                    'dias_sem_comprar' => $dias,
                    'compras' => $linha['compras'],
                    'valor' => round($linha['valor'], 2),
                    'r' => $r,
                    'f' => $f,
                    'm' => $m,
                    // Quanto o cliente gasta em relacao a media da base. E a unica das tres
                    // dimensoes que a tabela nao mostra crua, e "2,4x a media" se le sozinho —
                    // ao contrario de uma nota "4" que exige legenda.
                    'razao_valor' => round($razao, 2),
                    'segmento' => $this->segmento($r, $f, $linha['compras']),
                ];

                return $classificado;
            })
            ->sortByDesc('valor')
            ->values();
    }

    /**
     * Faixas fixas: percorre do maior corte ao menor e devolve a primeira que couber.
     * `menorEhMelhor` inverte a comparacao para a recencia (menos dias = melhor).
     */
    private function pontuar(float $valor, array $faixas, bool $menorEhMelhor = false): int
    {
        foreach ($faixas as [$corte, $nota]) {
            $cabe = $menorEhMelhor ? $valor <= $corte : $valor >= $corte;

            if ($cabe) {
                return $nota;
            }
        }

        return 1;
    }

    /** A ordem importa: "novo" precisa vir antes de "campeao" para nao roubar quem so comprou uma vez. */
    private function segmento(int $r, int $f, int $compras): SegmentoRfm
    {
        return match (true) {
            $compras === 1 && $r >= 4 => SegmentoRfm::Novo,
            $r >= 4 && $f >= 4 => SegmentoRfm::Campeao,
            $r >= 3 && $f >= 3 => SegmentoRfm::Fiel,
            $r <= 2 && $f >= 3 => SegmentoRfm::EmRisco,
            $r <= 2 => SegmentoRfm::Sumido,
            default => SegmentoRfm::Neutro,
        };
    }

    private function resumirSegmentos(Collection $clientes): array
    {
        $total = $clientes->count();

        return collect(SegmentoRfm::ordenados())
            ->map(function (SegmentoRfm $segmento) use ($clientes, $total): array {
                $doSegmento = $clientes->where('segmento', $segmento);
                $quantidade = $doSegmento->count();
                $receita = (float) $doSegmento->sum('valor');

                return [
                    'chave' => $segmento->value,
                    'label' => $segmento->label(),
                    'cor' => $segmento->cor(),
                    'descricao' => $segmento->descricao(),
                    'clientes' => $quantidade,
                    'percentual' => $total > 0 ? round($quantidade / $total * 100, 1) : 0.0,
                    'receita' => round($receita, 2),
                    'ticket_medio' => $quantidade > 0 ? round($receita / $quantidade, 2) : 0.0,
                ];
            })
            ->all();
    }
}
