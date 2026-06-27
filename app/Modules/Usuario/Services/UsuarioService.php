<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Services;

use App\Exceptions\NegocioException;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Actions\CriarUsuarioAction;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UsuarioService
{
    public function __construct(
        private CriarUsuarioAction $criarUsuario,
    ) {}

    public function listar(int $perPage = 20): LengthAwarePaginator
    {
        return Usuario::orderBy('nome')->paginate($perPage);
    }

    public function buscar(int $id): Usuario
    {
        return Usuario::findOrFail($id);
    }

    public function criar(Rede $rede, UsuarioData $data): Usuario
    {
        return $this->criarUsuario->executar($rede, $data);
    }

    public function atualizar(Usuario $usuario, UsuarioData $data): Usuario
    {
        if ($data->ativo === false && $usuario->ativo) {
            $this->garantirQuePodeInativar($usuario);
        }

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

        if ($data->empresas !== null) {
            // Pivot empresa_usuario tem rede_id obrigatorio.
            $sync = collect($data->empresas)
                ->mapWithKeys(fn ($id) => [(int) $id => ['rede_id' => $usuario->rede_id]])
                ->all();
            $usuario->empresas()->sync($sync);
        }

        return $usuario->fresh();
    }

    public function excluir(Usuario $usuario): void
    {
        $usuario->delete();
    }

    /**
     * Inativar um usuario tira a vaga dele no plano (e bloqueia o login), mas
     * nao pode deixar a rede sem acesso: nao se inativa a propria conta nem o
     * ultimo administrador ativo.
     */
    private function garantirQuePodeInativar(Usuario $usuario): void
    {
        if (auth()->id() === $usuario->id) {
            throw new NegocioException('Voce nao pode inativar a propria conta.');
        }

        if ($usuario->hasRole('Admin')) {
            $outrosAdminsAtivos = Usuario::whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
                ->where('ativo', true)
                ->whereKeyNot($usuario->id)
                ->count();

            if ($outrosAdminsAtivos === 0) {
                throw new NegocioException('A rede precisa de ao menos um administrador ativo.');
            }
        }
    }
}
