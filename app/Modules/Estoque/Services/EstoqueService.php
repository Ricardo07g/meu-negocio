<?php

namespace App\Modules\Estoque\Services;

use App\Modules\Estoque\DTOs\RegistrarMovimentoData;
use App\Enums\TipoMovimentoEstoque;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Produto\Models\Produto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    public function registrarMovimento(RegistrarMovimentoData $data): MovimentoEstoque
    {
        return DB::transaction(function () use ($data) {
            $produto = Produto::findOrFail($data->produto_id);

            $movimento = MovimentoEstoque::create([
                'produto_id' => $data->produto_id,
                'tipo' => $data->tipo,
                'quantidade' => $data->quantidade,
            ]);

            match ($data->tipo) {
                TipoMovimentoEstoque::Entrada => $produto->increment('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Saida => $produto->decrement('quantidade', $data->quantidade),
                TipoMovimentoEstoque::Ajuste => $produto->update(['quantidade' => $data->quantidade]),
            };

            return $movimento;
        });
    }

    public function listarMovimentos(?int $produtoId = null): Collection
    {
        $query = MovimentoEstoque::with('produto')->orderBy('created_at', 'desc');

        if ($produtoId) {
            $query->where('produto_id', $produtoId);
        }

        return $query->get();
    }
}
