<?php

namespace App\Modules\Produto\Services;

use App\Modules\Produto\DTOs\ProdutoData;
use App\Modules\Produto\Models\Produto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProdutoService
{
    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Produto::query()->with('categoria')->orderBy('nome');

        if (!empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('codigo_barras', 'like', "%{$q}%")
                    ->orWhere('descricao', 'like', "%{$q}%");
            });
        }

        if (!empty($filtros['categoria_produto_id'])) {
            $query->where('categoria_produto_id', $filtros['categoria_produto_id']);
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $query->where('ativo', (bool) $filtros['ativo']);
        }

        $this->aplicarEstoque($query, $filtros['estoque'] ?? null);

        if (!empty($filtros['preco_min'])) {
            $query->where('valor_venda', '>=', $filtros['preco_min']);
        }

        if (!empty($filtros['preco_max'])) {
            $query->where('valor_venda', '<=', $filtros['preco_max']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function aplicarEstoque($query, ?string $estoque): void
    {
        match ($estoque) {
            'zerado' => $query->where('quantidade', '<=', 0),
            'baixo' => $query->whereNotNull('estoque_minimo')
                ->whereColumn('quantidade', '<=', 'estoque_minimo')
                ->where('quantidade', '>', 0),
            'disponivel' => $query->where('quantidade', '>', 0),
            default => null,
        };
    }

    public function buscar(int $id): Produto
    {
        return Produto::findOrFail($id);
    }

    public function criar(ProdutoData $data): Produto
    {
        return Produto::create([
            'nome' => $data->nome,
            'codigo' => $data->codigo,
            'codigo_barras' => $data->codigo_barras,
            'descricao' => $data->descricao,
            'categoria_produto_id' => $data->categoria_produto_id,
            'quantidade' => $data->quantidade,
            'valor_custo' => $data->valor_custo,
            'valor_venda' => $data->valor_venda,
            'estoque_minimo' => $data->estoque_minimo,
            'unidade' => $data->unidade,
            'ativo' => $data->ativo,
            'observacoes' => $data->observacoes,
        ]);
    }

    public function atualizar(Produto $produto, ProdutoData $data): Produto
    {
        $produto->update([
            'nome' => $data->nome,
            'codigo' => $data->codigo,
            'codigo_barras' => $data->codigo_barras,
            'descricao' => $data->descricao,
            'categoria_produto_id' => $data->categoria_produto_id,
            'quantidade' => $data->quantidade,
            'valor_custo' => $data->valor_custo,
            'valor_venda' => $data->valor_venda,
            'estoque_minimo' => $data->estoque_minimo,
            'unidade' => $data->unidade,
            'ativo' => $data->ativo,
            'observacoes' => $data->observacoes,
        ]);

        return $produto->fresh();
    }

    public function excluir(Produto $produto): void
    {
        $produto->delete();
    }
}
