<?php

namespace App\Modules\Usuario\Services;

use App\Modules\Usuario\Actions\CriarUsuarioAction;
use App\Modules\Usuario\DTOs\AtualizarUsuarioData;
use App\Modules\Usuario\DTOs\CriarUsuarioData;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

class UsuarioService
{
    public function __construct(
        private CriarUsuarioAction $criarUsuario,
    ) {}

    public function listar(): Collection
    {
        return Usuario::all();
    }

    public function buscar(int $id): Usuario
    {
        return Usuario::findOrFail($id);
    }

    public function criar(Rede $rede, CriarUsuarioData $data): Usuario
    {
        return $this->criarUsuario->executar($rede, $data);
    }

    public function atualizar(Usuario $usuario, AtualizarUsuarioData $data): Usuario
    {
        $campos = [
            'nome' => $data->nome,
            'email' => $data->email,
        ];

        if ($data->password) {
            $campos['password'] = $data->password;
        }

        if ($data->empresa_id !== null) {
            $campos['empresa_id'] = $data->empresa_id;
        }

        if ($data->ativo !== null) {
            $campos['ativo'] = $data->ativo;
        }

        if ($data->atende !== null) {
            $campos['atende'] = $data->atende;
        }

        $usuario->update($campos);

        if ($data->papel) {
            $usuario->syncRoles([$data->papel]);
        }

        return $usuario->fresh();
    }

    public function excluir(Usuario $usuario): void
    {
        $usuario->delete();
    }
}
