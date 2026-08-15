<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Actions;

use App\Modules\Tenant\Actions\ValidarPlanoAction;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Usuario\Models\Usuario;

class CriarUsuarioAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
    ) {}

    public function executar(Rede $rede, UsuarioData $data): Usuario
    {
        $this->validarAssentos($rede, $data);

        $papel = $data->papel;

        $usuario = Usuario::create([
            'rede_id' => $rede->id,
            // A tela nao tem campo de empresa default — so os checkboxes do
            // pivot. Sem este fallback todo funcionario nascia com `empresa_id`
            // nulo e, no login, sem unidade para abrir.
            'empresa_id' => $data->empresa_id ?? $this->primeiraEmpresa($data),
            'nome' => $data->nome,
            'email' => $data->email,
            'password' => $data->password,
            'ativo' => true,
            'atende' => $data->atende ?? ($papel === 'Admin'),
        ]);

        $usuario->assignRole($papel);

        if ($data->empresas !== null) {
            // Pivot empresa_usuario tem rede_id obrigatorio.
            $sync = collect($data->empresas)
                ->mapWithKeys(fn ($id) => [(int) $id => ['rede_id' => $usuario->rede_id]])
                ->all();
            $usuario->empresas()->sync($sync);
        }

        return $usuario;
    }

    /** Empresa default = a primeira unidade a que o usuario recebeu acesso. */
    private function primeiraEmpresa(UsuarioData $data): ?int
    {
        $primeira = collect($data->empresas ?? [])->first();

        return $primeira !== null ? (int) $primeira : null;
    }

    /**
     * O limite de usuarios e da licenca de cada unidade, nao da rede: um usuario com
     * acesso a tres empresas ocupa um assento em cada uma.
     */
    private function validarAssentos(Rede $rede, UsuarioData $data): void
    {
        $ids = $data->empresas ?? array_filter([$data->empresa_id]);

        foreach ($rede->empresas()->whereIn('id', $ids)->get() as $empresa) {
            $this->validarPlano->executar($empresa, 'usuario');
        }
    }
}
