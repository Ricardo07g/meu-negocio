<?php

namespace App\Modules\Produto\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Produto\DTOs\AtualizarProdutoData;
use App\Modules\Produto\DTOs\CriarProdutoData;
use App\Modules\Produto\Requests\AtualizarProdutoRequest;
use App\Modules\Produto\Requests\CriarProdutoRequest;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Produto\Models\Produto;
use App\Modules\Estoque\Services\EstoqueService;
use App\Modules\Produto\Services\ProdutoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProdutoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private ProdutoService $service,
        private EstoqueService $estoqueService,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Produto::class);
            $produtos = $this->service->listar();

            return view('produto::index', compact('produtos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar produtos');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Produto::class);
            $categorias = CategoriaProduto::orderBy('nome')->get();

            return view('produto::create', compact('categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de produto');
        }
    }

    public function store(CriarProdutoRequest $request): RedirectResponse
    {
        try {
            $this->service->criar(CriarProdutoData::from($request->validated()));

            return redirect()->route('produtos.index')->with('sucesso', 'Produto criado com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar produto');
        }
    }

    public function show(Produto $produto): View|RedirectResponse
    {
        try {
            $this->authorize('view', $produto);
            $movimentos = $this->estoqueService->listarMovimentos($produto->id);

            return view('produto::show', compact('produto', 'movimentos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir produto');
        }
    }

    public function edit(Produto $produto): View|RedirectResponse
    {
        try {
            $this->authorize('update', $produto);
            $categorias = CategoriaProduto::orderBy('nome')->get();

            return view('produto::edit', compact('produto', 'categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de produto');
        }
    }

    public function update(AtualizarProdutoRequest $request, Produto $produto): RedirectResponse
    {
        try {
            $this->authorize('update', $produto);
            $this->service->atualizar($produto, AtualizarProdutoData::from($request->validated()));

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
}
