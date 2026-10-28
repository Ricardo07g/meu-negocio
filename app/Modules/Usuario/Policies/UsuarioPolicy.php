<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Policies;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Auth\Access\Response;

class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('usuario.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('usuario.criar');
    }

    public function update(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->can('usuario.editar')
            && $this->alcanca($usuario, $alvo);
    }

    /**
     * Quem enxerga quem.
     *
     * A regra anterior era `podeAcessarEmpresa($alvo->empresa_id)`, e isso
     * quebrava por dois motivos ao mesmo tempo:
     *
     * 1. **`usuarios.empresa_id` é preferência, não barreira de tenancy.** Ele
     *    guarda a empresa default ao logar; o conjunto real de acesso é o pivot
     *    `empresa_usuario`. Autorizar por ele é medir a régua errada.
     * 2. **Ele costuma ser nulo.** O formulário de usuário nem tem esse campo —
     *    só os checkboxes do pivot —, então todo funcionário criado pela tela
     *    nascia com `empresa_id = null`. E `podeAcessarEmpresa(null)` devolve
     *    `false` ANTES de checar o papel: nem o Admin conseguia editar, e o 403
     *    aparecia justamente em quem tem acesso a tudo.
     *
     * Usuario é rede-level (não usa `EmpresaTrait`): a fronteira é a rede, já
     * garantida acima. Aqui sobra o recorte entre colegas — um gerente de
     * unidade não mexe em quem não é da unidade dele.
     */
    private function alcanca(Usuario $usuario, Usuario $alvo): bool
    {
        if ($usuario->hasRole('Admin')) {
            return true;
        }

        $empresasDoAlvo = $alvo->empresas()->pluck('empresas.id')
            ->merge(array_filter([$alvo->empresa_id]))
            ->unique();

        // Alvo sem empresa nenhuma (nem pivot, nem default) só o Admin alcança:
        // não há unidade em comum para justificar o acesso.
        return $empresasDoAlvo->contains(fn ($id) => $usuario->podeAcessarEmpresa((int) $id));
    }

    /**
     * Zerar o 2FA de outro usuario (perdeu o celular) desliga um fator de
     * autenticacao dele: so o Admin, so dentro da rede, nunca de si mesmo (o
     * proprio 2FA se desliga no Meu Perfil, com senha) e **nunca de outro Admin** —
     * somado a trocar e-mail e senha, seria tomar a conta de quem manda na rede.
     * O 2FA de um Admin so o suporte redefine (comando `usuarios:redefinir-2fa`).
     */
    public function redefinirDoisFatores(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->id !== $alvo->id
            && $usuario->hasRole('Admin')
            && ! $alvo->hasRole('Admin');
    }

    /**
     * Rebaixar um Admin que tem 2FA ativo e coisa do suporte: senao o caminho
     * rebaixar -> redefinir o 2FA (alvo ja nao e Admin) -> promover de novo com
     * senha nova contornaria o `redefinirDoisFatores` e tomaria a conta. Manter o
     * papel (editar nome/e-mail) e rebaixar quem nao tem 2FA seguem permitidos.
     */
    public function alterarPapel(Usuario $usuario, Usuario $alvo, string $novoPapel): Response
    {
        $rebaixaAdminProtegido = $usuario->id !== $alvo->id
            && $novoPapel !== 'Admin'
            && $alvo->hasRole('Admin')
            && $alvo->temDoisFatoresAtivo();

        return $rebaixaAdminProtegido
            ? Response::deny('Este administrador usa verificação em duas etapas: para mudar o perfil dele, fale com o suporte.')
            : Response::allow();
    }

    public function delete(Usuario $usuario, Usuario $alvo): bool
    {
        return $usuario->rede_id === $alvo->rede_id
            && $usuario->can('usuario.excluir');
    }
}
