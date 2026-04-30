<?php

namespace App\Providers;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Agenda\Policies\AgendamentoPolicy;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Policies\CaixaPolicy;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Cliente\Policies\ClientePolicy;
use App\Modules\Despesa\Models\CategoriaDespesa;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Despesa\Policies\CategoriaDespesaPolicy;
use App\Modules\Despesa\Policies\DespesaPolicy;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Estoque\Policies\MovimentoEstoquePolicy;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Policies\PagamentoPolicy;
use App\Modules\PerfilAcesso\Policies\PerfilAcessoPolicy;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Produto\Models\Produto;
use App\Modules\Produto\Policies\CategoriaProdutoPolicy;
use App\Modules\Produto\Policies\ProdutoPolicy;
use App\Modules\Servico\Models\Servico;
use App\Modules\Servico\Policies\ServicoPolicy;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Policies\EmpresaPolicy;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Usuario\Policies\UsuarioPolicy;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Policies\VendaPacotePolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Map Model => Policy. Auto-discovery nao encontra policies em App\Modules\{X}\Policies,
     * por isso o registro e explicito.
     */
    protected array $policies = [
        Agendamento::class => AgendamentoPolicy::class,
        Caixa::class => CaixaPolicy::class,
        CategoriaDespesa::class => CategoriaDespesaPolicy::class,
        CategoriaProduto::class => CategoriaProdutoPolicy::class,
        Cliente::class => ClientePolicy::class,
        Despesa::class => DespesaPolicy::class,
        Empresa::class => EmpresaPolicy::class,
        MovimentoEstoque::class => MovimentoEstoquePolicy::class,
        Pagamento::class => PagamentoPolicy::class,
        Produto::class => ProdutoPolicy::class,
        Role::class => PerfilAcessoPolicy::class,
        Servico::class => ServicoPolicy::class,
        Usuario::class => UsuarioPolicy::class,
        VendaPacote::class => VendaPacotePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Route::resourceVerbs([
            'create' => 'novo',
            'edit' => 'editar',
        ]);

        Paginator::useBootstrapFive();
    }
}
