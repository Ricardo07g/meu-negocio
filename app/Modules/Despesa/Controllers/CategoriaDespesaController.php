<?php

namespace App\Modules\Despesa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Despesa\DTOs\CategoriaDespesaData;
use App\Modules\Despesa\Models\CategoriaDespesa;
use App\Modules\Despesa\Requests\SalvarCategoriaDespesaRequest;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoriaDespesaController extends Controller
{
    use TratamentoErros;

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', CategoriaDespesa::class);

            $filtros = $request->only(['q', 'ativo', 'com_despesas']);
            $query = CategoriaDespesa::withCount('despesas')->orderBy('descricao');

            if (!empty($filtros['q'])) {
                $query->where('descricao', 'like', '%' . $filtros['q'] . '%');
            }

            if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
                $query->where('ativo', (bool) $filtros['ativo']);
            }

            if (($filtros['com_despesas'] ?? null) === 'com') {
                $query->has('despesas');
            } elseif (($filtros['com_despesas'] ?? null) === 'sem') {
                $query->doesntHave('despesas');
            }

            $categorias = $query->get();

            return view('despesa::categorias.index', compact('categorias', 'filtros'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar categorias de despesa');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', CategoriaDespesa::class);

            return view('despesa::categorias.create');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de categoria');
        }
    }

    public function store(SalvarCategoriaDespesaRequest $request): RedirectResponse
    {
        try {
            $dados = CategoriaDespesaData::from($request->validated());
            CategoriaDespesa::create($dados->toArray());

            return redirect()->route('categorias-despesa.index')->with('sucesso', 'Categoria criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar categoria de despesa');
        }
    }

    public function edit(CategoriaDespesa $categorias_despesa): View|RedirectResponse
    {
        try {
            $this->authorize('update', $categorias_despesa);

            return view('despesa::categorias.edit', ['categoria' => $categorias_despesa]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de categoria');
        }
    }

    public function update(SalvarCategoriaDespesaRequest $request, CategoriaDespesa $categorias_despesa): RedirectResponse
    {
        try {
            $this->authorize('update', $categorias_despesa);
            $dados = CategoriaDespesaData::from($request->validated());
            $categorias_despesa->update($dados->toArray());

            return redirect()->route('categorias-despesa.index')->with('sucesso', 'Categoria atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar categoria de despesa');
        }
    }

    public function destroy(CategoriaDespesa $categorias_despesa): RedirectResponse
    {
        try {
            $this->authorize('delete', $categorias_despesa);
            $categorias_despesa->delete();

            return redirect()->route('categorias-despesa.index')->with('sucesso', 'Categoria excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir categoria de despesa');
        }
    }
}
