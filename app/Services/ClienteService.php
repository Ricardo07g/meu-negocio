<?php

namespace App\Services;

use App\Actions\Cliente\AtualizarClienteAction;
use App\Actions\Cliente\CriarClienteAction;
use App\DTO\Cliente\AtualizarClienteData;
use App\DTO\Cliente\CriarClienteData;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    public function __construct(
        private CriarClienteAction $criarCliente,
        private AtualizarClienteAction $atualizarCliente,
    ) {}

    public function listar(): Collection
    {
        return Cliente::all();
    }

    public function buscar(int $id): Cliente
    {
        return Cliente::findOrFail($id);
    }

    public function criar(CriarClienteData $data): Cliente
    {
        return $this->criarCliente->executar($data);
    }

    public function atualizar(Cliente $cliente, AtualizarClienteData $data): Cliente
    {
        return $this->atualizarCliente->executar($cliente, $data);
    }

    public function excluir(Cliente $cliente): void
    {
        $cliente->delete();
    }
}
