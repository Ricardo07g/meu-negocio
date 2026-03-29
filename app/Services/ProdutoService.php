<?php

namespace App\Services;

use App\DTO\Estoque\RegistrarMovimentoData;
use App\DTO\Produto\AtualizarProdutoData;
use App\DTO\Produto\CriarProdutoData;
use App\Enums\TipoMovimentoEstoque;
use App\Models\MovimentoEstoque;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

    public function registrarMovimento(RegistrarMovimentoData $data): MovimentoEstoque
    {
        return DB::transaction(function () use ($data) {
            $produto = Produto::findOrFail($data->produto_id);

            $movimento = MovimentoEstoque::create([
                'produto_id' => $data->produto_id,
                'tipo' => $data->tipo,
                'quantidade' => $data->quantidade,
            ]);

            // Atualizar quantidade do produto
            match ($data->tipo) {
                TipoMovimentoEstoque::Entrada => $produto->increment('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Saida => $produto->decrement('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Ajuste => $produto->update(['quantidade' => $data->quantidade]),
            };

            return $movimento;
        });
    }

    public function listarMovimentos(int $produtoId): Collection
    {
        return MovimentoEstoque::where('produto_id', $produtoId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
