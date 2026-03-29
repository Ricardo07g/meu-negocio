<?php

namespace App\Actions\Cliente;

use App\DTO\Cliente\CriarClienteData;
use App\Models\Cliente;

class CriarClienteAction
{
    public function executar(CriarClienteData $data): Cliente
    {
        return Cliente::create([
            'nome' => $data->nome,
            'telefone' => $data->telefone,
            'email' => $data->email,
            'observacoes' => $data->observacoes,
        ]);
    }
}
