<?php

declare(strict_types=1);

namespace App\Modules\Estoque\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Estoque\DTOs\RegistrarMovimentoData;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Estoque\Requests\RegistrarMovimentoRequest;
use App\Modules\Estoque\Services\EstoqueService;
use App\Modules\Produto\Models\Produto;
use App\Traits\{DefineEmpresaDeCriacao, TratamentoErros};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class MovimentoEstoqueController extends Controller
{
    use DefineEmpresaDeCriacao;
    use TratamentoErros;

    public function __construct(
        private EstoqueService $service,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', MovimentoEstoque::class);
            $filtros = $request->only(['q', 'produto_id', 'tipo', 'periodo_preset', 'data_inicio', 'data_fim']);
            $movimentos = $this->service->listarMovimentos($filtros);
            $produtos = Produto::orderBy('nome')->get(['id', 'nome']);

            return view('estoque::movimentos', compact('movimentos', 'filtros', 'produtos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar movimentos de estoque');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', MovimentoEstoque::class);
            $produtos = Produto::all();

            return view('estoque::criar-movimento', compact('produtos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de movimento de estoque');
        }
    }

    public function store(RegistrarMovimentoRequest $request): RedirectResponse
    {
        try {
            $empresaId = $request->filled('empresa_id') ? (int) $request->empresa_id : null;

            return $this->comEmpresaDeCriacao($empresaId, function () use ($request) {
                $this->service->registrarMovimento(RegistrarMovimentoData::from($request->validated()));

                return redirect()->route('movimentos-estoque.index')->with('sucesso', 'Movimento registrado com sucesso.');
            });
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar movimento de estoque');
        }
    }
}
