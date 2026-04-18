# Guia: Criar Novo Modulo

Passo a passo para criar um modulo completo seguindo a arquitetura do projeto.

## Estrutura de pastas

```
app/Modules/{NomeModulo}/
├── Models/
│   └── {NomeModel}.php
├── Controllers/
│   └── {NomeModel}Controller.php
├── Services/
│   └── {NomeModel}Service.php
├── Actions/
│   └── Criar{NomeModel}Action.php
│   └── Atualizar{NomeModel}Action.php
├── DTOs/
│   └── Criar{NomeModel}Data.php
│   └── Atualizar{NomeModel}Data.php
├── Requests/
│   └── Criar{NomeModel}Request.php
│   └── Atualizar{NomeModel}Request.php
├── Policies/
│   └── {NomeModel}Policy.php
├── Views/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
└── Migrations/
    └── {timestamp}_create_{tabela}_table.php
```

## Passo 1: Migration

- Criar em `app/Modules/{NomeModulo}/Migrations/`
- Sempre incluir: `rede_id` (FK redes) e `empresa_id` (FK empresas, se aplicavel)
- Indice composto: `[rede_id, empresa_id]`
- Seguir convencoes de `criar-migration.md`

## Passo 2: Model

```php
namespace App\Modules\{NomeModulo}\Models;

use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\SoftDeletes;

class {NomeModel} extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = '{tabela}';
    protected $fillable = ['rede_id', 'empresa_id', ...];
    protected $casts = [...];
}
```

- Usar `PertenceARede` sempre
- Usar `PertenceAEmpresa` quando dado pertence a empresa
- Definir fillable, casts, relacoes

## Passo 3: DTO (spatie/laravel-data)

```php
namespace App\Modules\{NomeModulo}\DTOs;

use Spatie\LaravelData\Data;

class Criar{NomeModel}Data extends Data
{
    public function __construct(
        public string $campo1,
        public ?string $campo2,
    ) {}
}
```

## Passo 4: Request

```php
namespace App\Modules\{NomeModulo}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Criar{NomeModel}Request extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'campo1' => 'required|string|max:200',
        ];
    }
}
```

## Passo 5: Policy

```php
namespace App\Modules\{NomeModulo}\Policies;

use App\Modules\Usuario\Models\Usuario;
use App\Modules\{NomeModulo}\Models\{NomeModel};

class {NomeModel}Policy
{
    public function viewAny(Usuario $user): bool { return $user->can('{recurso}.ver'); }
    public function view(Usuario $user, {NomeModel} $model): bool { return $user->can('{recurso}.ver'); }
    public function create(Usuario $user): bool { return $user->can('{recurso}.criar'); }
    public function update(Usuario $user, {NomeModel} $model): bool { return $user->can('{recurso}.editar'); }
    public function delete(Usuario $user, {NomeModel} $model): bool { return $user->can('{recurso}.excluir'); }
}
```

## Passo 6: Service

```php
namespace App\Modules\{NomeModulo}\Services;

class {NomeModel}Service
{
    public function listar() { return {NomeModel}::all(); }
    public function buscar(int $id) { return {NomeModel}::findOrFail($id); }
    public function criar(Criar{NomeModel}Data $data) { ... }
    public function atualizar({NomeModel} $model, Atualizar{NomeModel}Data $data) { ... }
    public function excluir({NomeModel} $model) { $model->delete(); }
}
```

## Passo 7: Controller

```php
namespace App\Modules\{NomeModulo}\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;

class {NomeModel}Controller extends Controller
{
    use TratamentoErros;

    public function __construct(private {NomeModel}Service $service) {}

    // index, create, store, show, edit, update, destroy
    // Sempre usar $this->authorize() ou policy
    // Sempre usar Request para validacao
    // Sempre usar Service para logica
}
```

## Passo 8: Views

- Usar layout: `@extends('layouts.app')`
- Seguir padrao das views existentes
- Usar template Duralux para componentes UI

## Passo 9: Rotas

Adicionar em `routes/web.php` dentro do grupo `verificar.empresa`:

```php
Route::resource('{recurso}', {NomeModel}Controller::class);
```

## Passo 10: Permissoes

- Criar permissoes no seeder: `{recurso}.ver`, `{recurso}.criar`, `{recurso}.editar`, `{recurso}.excluir`
- Atribuir aos papeis conforme matriz
- Ver: `adicionar-permissao.md`

## Checklist

- [ ] Migration com rede_id + empresa_id
- [ ] Model com traits de tenant
- [ ] DTOs de criacao e atualizacao
- [ ] Requests de validacao
- [ ] Policy com permissoes
- [ ] Service com CRUD
- [ ] Controller usando Service
- [ ] Views (index, create, edit, show)
- [ ] Rotas no web.php
- [ ] Permissoes criadas e atribuidas
- [ ] Menu no layout (se aplicavel)
