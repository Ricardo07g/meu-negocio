<?php

namespace App\Modules\Produto\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Produto\DTOs\CategoriaProdutoData;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Produto\Requests\SalvarCategoriaProdutoRequest;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoriaProdutoController extends Controller
{
    use TratamentoErros;

    public function index(): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', CategoriaProduto::class);
            $categorias = CategoriaProduto::orderBy('nome')->get();

            return view('produto::categorias.index', compact('categorias'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar categorias de produto');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', CategoriaProduto::class);

            return view('produto::categorias.create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de categoria');
        }
    }

    public function store(SalvarCategoriaProdutoRequest $request): RedirectResponse
    {
        try {
            $dados = CategoriaProdutoData::from($request->validated());
            CategoriaProduto::create($dados->toArray());

            return redirect()->route('categorias-produto.index')->with('sucesso', 'Categoria criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar categoria de produto');
        }
    }

    public function edit(CategoriaProduto $categorias_produto): View|RedirectResponse
    {
        try {
            $this->authorize('update', $categorias_produto);

            return view('produto::categorias.edit', ['categoria' => $categorias_produto]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de categoria');
        }
    }

    public function update(SalvarCategoriaProdutoRequest $request, CategoriaProduto $categorias_produto): RedirectResponse
    {
        try {
            $this->authorize('update', $categorias_produto);
            $dados = CategoriaProdutoData::from($request->validated());
            $categorias_produto->update($dados->toArray());

            return redirect()->route('categorias-produto.index')->with('sucesso', 'Categoria atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar categoria de produto');
        }
    }

    public function destroy(CategoriaProduto $categorias_produto): RedirectResponse
    {
        try {
            $this->authorize('delete', $categorias_produto);
            $categorias_produto->delete();

            return redirect()->route('categorias-produto.index')->with('sucesso', 'Categoria excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir categoria de produto');
        }
    }
}
