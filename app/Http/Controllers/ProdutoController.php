<?php

namespace App\Http\Controllers;

use App\DTO\Produto\AtualizarProdutoData;
use App\DTO\Produto\CriarProdutoData;
use App\Http\Requests\Produto\AtualizarProdutoRequest;
use App\Http\Requests\Produto\CriarProdutoRequest;
use App\Models\Produto;
use App\Services\ProdutoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProdutoController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private ProdutoService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Produto::class);
            $produtos = $this->service->listar();

            return view('produtos.index', compact('produtos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar produtos');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Produto::class);

            return view('produtos.create');
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
            $movimentos = $this->service->listarMovimentos($produto->id);

            return view('produtos.show', compact('produto', 'movimentos'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir produto');
        }
    }

    public function edit(Produto $produto): View|RedirectResponse
    {
        try {
            $this->authorize('update', $produto);

            return view('produtos.edit', compact('produto'));
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
