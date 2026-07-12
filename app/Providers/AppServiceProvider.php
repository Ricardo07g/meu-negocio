<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Agenda\Policies\AgendamentoPolicy;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Policies\CaixaPolicy;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Cliente\Policies\ClientePolicy;
use App\Modules\Despesa\Models\{CategoriaDespesa, Despesa};
use App\Modules\Despesa\Policies\{CategoriaDespesaPolicy, DespesaPolicy};
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Estoque\Policies\MovimentoEstoquePolicy;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Policies\PagamentoPolicy;
use App\Modules\PerfilAcesso\Policies\PerfilAcessoPolicy;
use App\Modules\Produto\Models\{CategoriaProduto, Produto};
use App\Modules\Produto\Policies\{CategoriaProdutoPolicy, ProdutoPolicy};
use App\Modules\Servico\Models\Servico;
use App\Modules\Servico\Policies\ServicoPolicy;
use App\Modules\Tenant\Models\{Empresa, Fatura};
use App\Modules\Tenant\Policies\{EmpresaPolicy, FaturaPolicy};
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Usuario\Policies\UsuarioPolicy;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use App\Modules\Venda\Policies\{VendaEtapasPolicy, VendaProdutoPolicy};
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\{Gate, Route};
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
        Fatura::class => FaturaPolicy::class,
        MovimentoEstoque::class => MovimentoEstoquePolicy::class,
        Pagamento::class => PagamentoPolicy::class,
        Produto::class => ProdutoPolicy::class,
        Role::class => PerfilAcessoPolicy::class,
        Servico::class => ServicoPolicy::class,
        Usuario::class => UsuarioPolicy::class,
        VendaEtapas::class => VendaEtapasPolicy::class,
        VendaProduto::class => VendaProdutoPolicy::class,
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
