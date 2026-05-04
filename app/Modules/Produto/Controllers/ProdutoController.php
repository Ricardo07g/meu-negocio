<?php

namespace App\Modules\Produto\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Estoque\Services\EstoqueService;
use App\Modules\Produto\DTOs\ProdutoData;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Produto\Models\Produto;
use App\Modules\Produto\Requests\SalvarProdutoRequest;
use App\Modules\Produto\Services\ProdutoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProdutoController extends Controller
{
    use TratamentoErros;

    public function __construct(private ProdutoService $service, private EstoqueService $estoqueService) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Produto::class);
            $filtros = $request->only(['q', 'categoria_produto_id', 'ativo', 'estoque', 'preco_min', 'preco_max']);
            $produtos = $this->service->listar($filtros);
            $categorias = CategoriaProduto::where('ativo', true)->orderBy('descricao')->get();

            return view('produto::index', compact('produtos', 'filtros', 'categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar produtos');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Produto::class);
            $categorias = CategoriaProduto::where('ativo', true)->orderBy('descricao')->get();

            return view('produto::create', compact('categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de produto');
        }
    }

    public function store(SalvarProdutoRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(ProdutoData::from($request->validated()));

            return redirect()->route('produtos.index')->with('sucesso', 'Produto criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar produto');
        }
    }

    public function show(Produto $produto): View|RedirectResponse
    {
        try {
            $this->authorize('view', $produto);
            $movimentos = $this->estoqueService->listarMovimentos(['produto_id' => $produto->id]);

            return view('produto::show', compact('produto', 'movimentos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir produto');
        }
    }

    public function edit(Produto $produto): View|RedirectResponse
    {
        try {
            $this->authorize('update', $produto);
            $categorias = CategoriaProduto::where('ativo', true)->orderBy('descricao')->get();

            return view('produto::edit', compact('produto', 'categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de produto');
        }
    }

    public function update(SalvarProdutoRequest $request, Produto $produto): RedirectResponse
    {
        try {
            $this->authorize('update', $produto);
            $this->service->atualizar($produto, ProdutoData::from($request->validated()));

            return redirect()->route('produtos.index')->with('sucesso', 'Produto atualizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar produto');
        }
    }

    public function destroy(Produto $produto): RedirectResponse
    {
        try {
            $this->authorize('delete', $produto);
            $this->service->excluir($produto);

            return redirect()->route('produtos.index')->with('sucesso', 'Produto excluído com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir produto');
        }
    }

    public function buscar(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $produtos = Produto::where('ativo', true)
            ->where(function ($query) use ($q) {
                $query->where('nome', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'nome', 'valor_venda', 'quantidade']);

        return response()->json($produtos);
    }
}
