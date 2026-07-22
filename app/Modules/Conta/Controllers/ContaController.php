<?php

declare(strict_types=1);

namespace App\Modules\Conta\Controllers;

use App\Enums\{FormatoExportacao, StatusExportacao, TipoConta, TipoLancamento};
use App\Http\Controllers\Controller;
use App\Modules\Conta\DTOs\ContaData;
use App\Modules\Conta\Models\{Conta, Exportacao};
use App\Modules\Conta\Requests\{SalvarContaRequest, SalvarExportacaoRequest};
use App\Modules\Conta\Services\{ContaService, ExportacaoService};
use App\Support\ContextoEmpresa;
use App\Traits\TratamentoErros;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private ContaService $service,
        private ExportacaoService $exportacaoService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Conta::class);

            $filtros = $request->only(['q', 'ativo', 'tipo']);

            return view('conta::index', [
                'contas' => $this->service->listar($filtros),
                'filtros' => $filtros,
                'tipos' => TipoConta::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar contas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Conta::class);

            return view('conta::create', ['tipos' => TipoConta::cases()]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de conta');
        }
    }

    public function store(SalvarContaRequest $request): RedirectResponse
    {
        try {
            $this->authorize('create', Conta::class);

            $empresaId = ContextoEmpresa::resolver() ?? $request->user()->empresa_id;
            if (! $empresaId) {
                return redirect()->back()->withInput()
                    ->with('erro', 'Selecione uma empresa no topo para cadastrar a conta.');
            }

            $this->service->criar(ContaData::from($request->validated()), (int) $empresaId);

            return redirect()->route('contas.index')->with('sucesso', 'Conta criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar conta');
        }
    }

    public function extrato(Request $request, Conta $conta): View|RedirectResponse
    {
        try {
            $this->authorize('view', $conta);

            // Tela mostra UM mes (?mes=YYYY-MM; default mes atual). Periodos maiores saem por exportacao.
            $mesParam = (string) $request->query('mes', '');
            $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesParam)
                ? Carbon::createFromFormat('Y-m-d', $mesParam.'-01')->startOfMonth()
                : now()->startOfMonth();

            $inicio = $mes->copy()->startOfMonth();
            $fim = $mes->copy()->endOfMonth();

            $lancamentos = $conta->lancamentos()
                ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
                ->orderByDesc('data')->orderByDesc('id')
                ->get();

            $entradas = round((float) $conta->lancamentos()
                ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
                ->where('tipo', TipoLancamento::Credito->value)->sum('valor'), 2);
            $saidas = round((float) $conta->lancamentos()
                ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
                ->where('tipo', TipoLancamento::Debito->value)->sum('valor'), 2);

            // Exportacoes recentes desta conta (para o painel de download por periodo).
            $exportacoes = Exportacao::where('conta_id', $conta->id)
                ->orderByDesc('id')->limit(8)->get();

            return view('conta::extrato', [
                'conta' => $conta,
                'lancamentos' => $lancamentos,
                'saldo' => $conta->saldo(),
                'mesSelecionado' => $mes,
                'mesAnterior' => $mes->copy()->subMonth()->format('Y-m'),
                'mesProximo' => $mes->copy()->addMonth()->format('Y-m'),
                'ehMesAtual' => $mes->isSameMonth(now()),
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldoMes' => round($entradas - $saidas, 2),
                'exportacoes' => $exportacoes,
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar extrato da conta');
        }
    }

    /**
     * Solicita a exportacao do extrato da conta num periodo (planilha). Cria o
     * pedido (status processando) e enfileira o job que gera o arquivo (ADR-0012).
     */
    public function exportar(SalvarExportacaoRequest $request, Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('view', $conta);

            $this->exportacaoService->solicitar(
                conta: $conta,
                formato: FormatoExportacao::from($request->string('formato')->toString()),
                de: $request->date('de')->toDateString(),
                ate: $request->date('ate')->toDateString(),
                usuarioId: (int) auth()->id(),
            );

            return redirect()
                ->route('contas.extrato', ['conta' => $conta, 'mes' => $request->query('mes')])
                ->with('sucesso', 'Exportação em processamento. Atualize a página em instantes para baixar.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao solicitar exportação');
        }
    }

    /**
     * Download AUTENTICADO do arquivo de exportacao (dado financeiro — nunca URL
     * publica). O binding de {exportacao} ja e escopado por empresa (EmpresaTrait);
     * checamos ainda que a exportacao e desta conta e que esta pronta.
     */
    public function baixarExportacao(Conta $conta, Exportacao $exportacao): StreamedResponse
    {
        $this->authorize('view', $conta);

        abort_unless($exportacao->conta_id === $conta->id, 404);
        abort_unless($exportacao->estaPronta(), 404);

        return Storage::disk((string) $exportacao->disco)
            ->download((string) $exportacao->caminho, $exportacao->nome_arquivo);
    }

    /** Exclusao manual de uma exportacao: apaga o arquivo no storage + o registro. */
    public function excluirExportacao(Conta $conta, Exportacao $exportacao): RedirectResponse
    {
        // Fora do try: authorize (403) e o guard de posse (404) devem propagar como status HTTP.
        $this->authorize('view', $conta);
        abort_unless($exportacao->conta_id === $conta->id, 404);

        try {
            if ($exportacao->disco && $exportacao->caminho) {
                Storage::disk($exportacao->disco)->delete($exportacao->caminho);
            }
            $exportacao->delete();

            return back()->with('sucesso', 'Exportação removida.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao remover exportação');
        }
    }

    /**
     * Status das exportacoes recentes da conta (JSON). A tela do extrato faz polling
     * via AJAX para atualizar os badges e liberar o "Baixar" sem recarregar a pagina.
     */
    public function exportacoesStatus(Conta $conta): JsonResponse
    {
        $this->authorize('view', $conta);

        $registros = Exportacao::where('conta_id', $conta->id)
            ->orderByDesc('id')->limit(8)->get();

        return response()->json([
            'processando' => $registros->contains(fn (Exportacao $e) => $e->status === StatusExportacao::Processando),
            'exportacoes' => $registros->map(fn (Exportacao $e) => [
                'id' => $e->id,
                'periodo' => $e->periodo_inicio->format('d/m/Y').' – '.$e->periodo_fim->format('d/m/Y'),
                'formato' => $e->formato->label(),
                'status' => $e->status->value,
                'statusLabel' => $e->status->label(),
                'cor' => $e->status->cor(),
                'expiraEm' => $e->expiraEm()->format('d/m/Y H:i'),
                'pronta' => $e->estaPronta(),
                'podeExcluir' => $e->podeExcluir(),
                'urlDownload' => $e->estaPronta()
                    ? route('contas.exportacoes.baixar', ['conta' => $conta, 'exportacao' => $e])
                    : null,
                'urlExcluir' => route('contas.exportacoes.excluir', ['conta' => $conta, 'exportacao' => $e]),
            ])->values(),
        ]);
    }

    public function edit(Conta $conta): View|RedirectResponse
    {
        try {
            $this->authorize('update', $conta);

            return view('conta::edit', [
                'conta' => $conta,
                'tipos' => TipoConta::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de conta');
        }
    }

    public function update(SalvarContaRequest $request, Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('update', $conta);

            // A conta Caixa do sistema so aceita renomear; as demais atualizam pelo DTO.
            if ($conta->ehProtegida()) {
                $this->service->renomear($conta, (string) $request->validated('nome'));
            } else {
                $this->service->atualizar($conta, ContaData::from($request->validated()));
            }

            return redirect()->route('contas.index')->with('sucesso', 'Conta atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar conta');
        }
    }

    public function destroy(Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('delete', $conta);
            $this->service->excluir($conta);

            return redirect()->route('contas.index')->with('sucesso', 'Conta excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir conta');
        }
    }

    public function inativar(Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('update', $conta);
            $this->service->inativar($conta);

            return redirect()->route('contas.index')->with('sucesso', 'Conta inativada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao inativar conta');
        }
    }

    public function reativar(Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('update', $conta);
            $this->service->reativar($conta);

            return redirect()->route('contas.index')->with('sucesso', 'Conta reativada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao reativar conta');
        }
    }
}
