<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = basename($modulePath);
            $moduleKey = strtolower($moduleName);

            // Registrar views do módulo (referenciadas como 'modulo::view')
            $viewsPath = $modulePath . '/Views';
            if (File::isDirectory($viewsPath)) {
                $this->loadViewsFrom($viewsPath, $moduleKey);
            }

            // Registrar migrations do módulo
            $migrationsPath = $modulePath . '/Migrations';
            if (File::isDirectory($migrationsPath) && count(File::files($migrationsPath)) > 0) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}
