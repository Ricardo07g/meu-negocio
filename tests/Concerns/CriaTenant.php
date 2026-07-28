<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\{StatusRede, TipoFormaPagamento};
use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Tenant\Models\{Empresa, Plano, Rede};
use App\Modules\Usuario\Models\Usuario;
use Database\Seeders\{PermissaoSeeder, PlanoSeeder};
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helpers de bootstrap multi-tenant para testes Feature.
 *
 * Cria rede + empresa + usuario admin no padrao do RedeService,
 * sem chamar o service real (mais rapido e isolado de seeds de catalogo).
 *
 * Use `criarRedeAutenticada()` para o cenario padrao "ja logado como
 * admin" e `criarRede()` quando precisar de uma segunda rede para
 * testes de isolamento multi-tenant.
 */
trait CriaTenant
{
    /**
     * Cria uma rede completa (Rede + Empresa + Admin) e ja autentica
     * o usuario admin. Retorna o struct para uso em asserts.
     *
     * @return array{rede: Rede, empresa: Empresa, usuario: Usuario}
     */
    protected function criarRedeAutenticada(): array
    {
        $contexto = $this->criarRede();

        $this->actingAs($contexto['usuario']);

        // Popula a sessao de empresas atuais como o middleware faria.
        session(['empresas_atuais' => [$contexto['empresa']->id]]);

        return $contexto;
    }

    /**
     * Cria a tripla Rede + Empresa + Admin sem autenticar.
     * Util para cenarios de isolamento multi-tenant onde precisamos
     * de mais de uma rede.
     *
     * @return array{rede: Rede, empresa: Empresa, usuario: Usuario}
     */
    protected function criarRede(?string $sufixo = null, string $planoSlug = Plano::PRO): array
    {
        $this->garantirSeedsBase();

        $sufixo ??= (string) random_int(1, 999_999);

        // A licenca e da empresa, nao da rede. Pro por padrao: e o que o registro real
        // entrega (trial no Pro) e o que mantem estoque/financeiro ligados nos fluxos.
        $plano = Plano::where('slug', $planoSlug)->firstOrFail();

        $rede = Rede::create([
            'nome' => "Rede Teste {$sufixo}",
            'status' => StatusRede::Ativa,
        ]);

        $empresa = Empresa::create([
            'rede_id' => $rede->id,
            'plano_id' => $plano->id,
            'nome' => "Empresa {$sufixo}",
        ]);

        $usuario = Usuario::create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'nome' => "Admin {$sufixo}",
            'email' => "admin{$sufixo}@teste.com",
            'password' => Hash::make('password'),
            'ativo' => true,
            'atende' => true,
        ]);

        $usuario->assignRole('Admin');

        // Contas financeiras padrão da empresa (Caixa + Banco) — antes das formas,
        // que ligam cartão/pix à conta bancária (espelha CriarEmpresaAction).
        app(ContaService::class)->semearPadrao($rede->id, $empresa->id);

        // Formas de pagamento padrão da empresa (Dinheiro/Pix; Débito/Crédito recebível).
        app(FormaPagamentoService::class)->semearPadrao($rede->id, $empresa->id);

        return compact('rede', 'empresa', 'usuario');
    }

    /**
     * Cria uma unidade extra na rede para cenarios multi-empresa.
     *
     * Toda empresa e uma licenca: `plano_id` e obrigatorio. Pro por padrao, para os
     * fluxos de estoque/financeiro seguirem disponiveis. Nao semeia contas nem formas
     * de pagamento — os testes que precisam disso chamam os services explicitamente.
     */
    protected function criarEmpresaExtra(int $redeId, string $nome): Empresa
    {
        return Empresa::create([
            'rede_id' => $redeId,
            'plano_id' => Plano::where('slug', Plano::PRO)->firstOrFail()->id,
            'nome' => $nome,
        ]);
    }

    /**
     * Retorna uma forma de pagamento padrão da rede pelo tipo (a da 1ª empresa
     * da rede, por padrão). As formas são empresa-level; ordenamos por
     * empresa_id para ser determinístico em redes com múltiplas empresas.
     * (Sem global scope: útil mesmo antes/depois do actingAs.)
     */
    protected function formaPagamento(Rede $rede, TipoFormaPagamento $tipo = TipoFormaPagamento::Dinheiro): FormaPagamento
    {
        return FormaPagamento::withoutGlobalScopes()
            ->where('rede_id', $rede->id)
            ->where('tipo', $tipo->value)
            ->orderBy('empresa_id')
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * Cria um usuario nao-admin com role e empresa vinculada via pivot.
     */
    protected function criarUsuarioComum(Rede $rede, Empresa $empresa, string $papel = 'Profissional'): Usuario
    {
        $this->garantirRole($papel);

        $usuario = Usuario::create([
            'rede_id' => $rede->id,
            'empresa_id' => $empresa->id,
            'nome' => "Usuario {$papel} ".random_int(1, 9999),
            'email' => 'user'.random_int(1, 999_999).'@teste.com',
            'password' => Hash::make('password'),
            'ativo' => true,
            'atende' => true,
        ]);

        $usuario->assignRole($papel);
        $usuario->empresas()->sync([$empresa->id => ['rede_id' => $rede->id]]);

        return $usuario;
    }

    /**
     * Garante que os seeds de Plano + Permissao + Role Admin foram aplicados.
     * Idempotente: pode ser chamado em multiplos testes.
     */
    protected function garantirSeedsBase(): void
    {
        if (Plano::where('slug', Plano::PRO)->doesntExist()) {
            $this->seed(PlanoSeeder::class);
        }

        if (Role::where('name', 'Admin')->doesntExist()) {
            $this->seed(PermissaoSeeder::class);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function garantirRole(string $nome): void
    {
        $this->garantirSeedsBase();

        if (Role::where('name', $nome)->doesntExist()) {
            Role::create(['name' => $nome, 'guard_name' => 'web']);
        }
    }
}
