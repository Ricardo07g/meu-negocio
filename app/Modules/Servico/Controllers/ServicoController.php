<?php

namespace App\Modules\Servico\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Servico\DTOs\ServicoData;
use App\Modules\Servico\Models\Servico;
use App\Modules\Servico\Requests\SalvarServicoRequest;
use App\Modules\Servico\Services\ServicoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private ServicoService $service,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Servico::class);
            $filtros = $request->only(['q', 'tipo', 'valor_min', 'valor_max', 'duracao_min', 'duracao_max']);
            $servicos = $this->service->listar($filtros);

            return view('servico::index', compact('servicos', 'filtros'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar serviços');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Servico::class);

            return view('servico::create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de serviço');
        }
    }

    public function store(SalvarServicoRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(ServicoData::from($request->validated()));

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar serviço');
        }
    }

    public function show(Servico $servico): View|RedirectResponse
    {
        try {
            $this->authorize('view', $servico);

            $agendamentos = $servico->agendamentos()
                ->with(['cliente', 'atendente'])
                ->orderByDesc('inicio')
                ->paginate(10, pageName: 'pageAgenda');

            $vendasEtapas = $servico->isEtapas()
                ? $servico->vendasEtapas()
                    ->with('cliente')
                    ->orderByDesc('created_at')
                    ->paginate(10, pageName: 'pageEtapas')
                : null;

            return view('servico::show', compact('servico', 'agendamentos', 'vendasEtapas'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir serviço');
        }
    }

    public function edit(Servico $servico): View|RedirectResponse
    {
        try {
            $this->authorize('update', $servico);

            return view('servico::edit', compact('servico'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de serviço');
        }
    }

    public function update(SalvarServicoRequest $request, Servico $servico): RedirectResponse
    {
        try {
            $this->authorize('update', $servico);
            $this->service->atualizar($servico, ServicoData::from($request->validated()));

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar serviço');
        }
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        try {
            $this->authorize('delete', $servico);
            $this->service->excluir($servico);

            return redirect()->route('servicos.index')->with('sucesso', 'Serviço excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir serviço');
        }
    }

    public function buscar(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $servicos = Servico::where('nome', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'nome', 'tipo', 'duracao', 'valor', 'qtd_etapas']);

        return response()->json($servicos);
    }
}
