<?php

namespace App\Modules\Estoque\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Estoque\DTOs\RegistrarMovimentoData;
use App\Modules\Estoque\Requests\RegistrarMovimentoRequest;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use App\Modules\Estoque\Services\EstoqueService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MovimentoEstoqueController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private EstoqueService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', MovimentoEstoque::class);
            $movimentos = MovimentoEstoque::with('produto')->orderBy('created_at', 'desc')->get();

            return view('estoque::movimentos', compact('movimentos'));
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
            $this->service->registrarMovimento(RegistrarMovimentoData::from($request->validated()));

            return redirect()->route('movimentos-estoque.index')->with('sucesso', 'Movimento registrado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar movimento de estoque');
        }
    }
}
