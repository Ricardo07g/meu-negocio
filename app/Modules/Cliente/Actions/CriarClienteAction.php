<?php

namespace App\Modules\Cliente\Actions;

use App\Modules\Cliente\DTOs\CriarClienteData;
use App\Modules\Cliente\Models\Cliente;
use Carbon\Carbon;

class CriarClienteAction
{
    public function executar(CriarClienteData $data): Cliente
    {
        return Cliente::create([
            'nome' => $data->nome,
            'telefone' => $data->telefone,
            'telefone_whatsapp' => $data->telefone_whatsapp ?? false,
            'email' => $data->email,
            'data_nascimento' => $data->data_nascimento
                ? Carbon::createFromFormat('d/m/Y', $data->data_nascimento)
                : null,
            'cpf' => $data->cpf,
            'sexo' => $data->sexo,
            'cep' => $data->cep,
            'estado' => $data->estado,
            'cidade' => $data->cidade,
            'bairro' => $data->bairro,
            'logradouro' => $data->logradouro,
            'numero' => $data->numero,
            'complemento' => $data->complemento,
            'observacoes' => $data->observacoes,
        ]);
    }
}
