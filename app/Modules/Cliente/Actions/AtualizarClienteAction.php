<?php

namespace App\Modules\Cliente\Actions;

use App\Modules\Cliente\DTOs\AtualizarClienteData;
use App\Modules\Cliente\Models\Cliente;

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
