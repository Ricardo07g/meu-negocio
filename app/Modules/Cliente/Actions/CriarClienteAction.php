<?php

namespace App\Modules\Cliente\Actions;

use App\Modules\Cliente\DTOs\CriarClienteData;
use App\Modules\Cliente\Models\Cliente;

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
