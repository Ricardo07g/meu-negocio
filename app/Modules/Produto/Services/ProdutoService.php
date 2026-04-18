<?php

namespace App\Modules\Produto\Services;

use App\Modules\Produto\DTOs\ProdutoData;
use App\Modules\Produto\Models\Produto;
use Illuminate\Database\Eloquent\Collection;

class ProdutoService
{
    public function listar(): Collection
    {
        return Produto::all();
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
