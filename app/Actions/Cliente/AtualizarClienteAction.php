<?php

namespace App\Actions\Cliente;

use App\DTO\Cliente\AtualizarClienteData;
use App\Models\Cliente;

class AtualizarClienteAction
{
    public function executar(Cliente $cliente, AtualizarClienteData $data): Cliente
    {
        $cliente->update([
            'nome' => $data->nome,
            'telefone' => $data->telefone,
            'email' => $data->email,
            'observacoes' => $data->observacoes,
        ]);

        return $cliente->fresh();
    }
}
