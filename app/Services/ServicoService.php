<?php

namespace App\Services;

use App\DTO\Servico\AtualizarServicoData;
use App\DTO\Servico\CriarServicoData;
use App\Models\Servico;
use Illuminate\Database\Eloquent\Collection;

class ServicoService
{
    public function listar(): Collection
    {
        return Servico::all();
    }

    public function buscar(int $id): Servico
    {
        return Servico::findOrFail($id);
    }

    public function criar(CriarServicoData $data): Servico
    {
        return Servico::create([
            'nome' => $data->nome,
            'duracao' => $data->duracao,
            'valor' => $data->valor,
            'tipo' => $data->tipo,
            'qtd_sessoes' => $data->qtd_sessoes,
            'descricao' => $data->descricao,
        ]);
    }

    public function atualizar(Servico $servico, AtualizarServicoData $data): Servico
    {
        $servico->update([
            'nome' => $data->nome,
            'duracao' => $data->duracao,
            'valor' => $data->valor,
            'tipo' => $data->tipo,
            'qtd_sessoes' => $data->qtd_sessoes,
            'descricao' => $data->descricao,
        ]);

        return $servico->fresh();
    }

    public function excluir(Servico $servico): void
    {
        $servico->delete();
    }
}
