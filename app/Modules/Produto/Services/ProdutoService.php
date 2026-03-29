<?php

namespace App\Modules\Produto\Services;

use App\Modules\Produto\DTOs\AtualizarProdutoData;
use App\Modules\Produto\DTOs\CriarProdutoData;
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

    public function criar(CriarProdutoData $data): Produto
    {
        return Produto::create([
            'nome' => $data->nome,
            'quantidade' => $data->quantidade,
            'valor' => $data->valor,
        ]);
    }

    public function atualizar(Produto $produto, AtualizarProdutoData $data): Produto
    {
        $produto->update([
            'nome' => $data->nome,
            'quantidade' => $data->quantidade,
            'valor' => $data->valor,
        ]);

        return $produto->fresh();
    }

    public function excluir(Produto $produto): void
    {
        $produto->delete();
    }

}
