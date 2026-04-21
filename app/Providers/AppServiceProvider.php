<?php

namespace App\Providers;

use App\Modules\Papel\Policies\PapelPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, PapelPolicy::class);

        Route::resourceVerbs([
            'create' => 'novo',
            'edit' => 'editar',
        ]);

        Paginator::useBootstrapFive();
    }
}
