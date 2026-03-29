<?php

namespace App\Services;

use App\Actions\Empresa\CriarEmpresaAction;
use App\DTO\Empresa\AtualizarEmpresaData;
use App\DTO\Empresa\CriarEmpresaData;
use App\Models\Rede;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Collection;

class EmpresaService
{
    public function __construct(
        private CriarEmpresaAction $criarEmpresa,
    ) {}

    public function listar(): Collection
    {
        return Empresa::all();
    }

    public function buscar(int $id): Empresa
    {
        return Empresa::findOrFail($id);
    }

    public function criar(Rede $rede, CriarEmpresaData $data): Empresa
    {
        return $this->criarEmpresa->executar($rede, $data);
    }

    public function atualizar(Empresa $empresa, AtualizarEmpresaData $data): Empresa
    {
        $empresa->update([
            'nome' => $data->nome,
            'documento' => $data->documento,
            'telefone' => $data->telefone,
            'email' => $data->email,
        ]);

        return $empresa->fresh();
    }

    public function excluir(Empresa $empresa): void
    {
        $empresa->delete();
    }
}
