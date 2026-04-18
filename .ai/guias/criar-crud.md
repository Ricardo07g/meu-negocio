# Guia: Criar CRUD Completo

Template de referencia para criar um CRUD seguindo a arquitetura.

## Fluxo do CRUD

```
Request → Controller → Service → Model/Action → Response
            ↑             ↑
         Request        DTO
         (validacao)    (transporte)
```

## Controller (padrao)

```php
class ExemploController extends Controller
{
    use TratamentoErros;

    public function __construct(private ExemploService $service) {}

    public function index()
    {
        return $this->tratarErros(function () {
            $this->authorize('viewAny', Exemplo::class);
            $itens = $this->service->listar();
            return view('exemplo::index', compact('itens'));
        });
    }

    public function create()
    {
        return $this->tratarErros(function () {
            $this->authorize('create', Exemplo::class);
            return view('exemplo::create');
        });
    }

    public function store(CriarExemploRequest $request)
    {
        return $this->tratarErros(function () use ($request) {
            $this->authorize('create', Exemplo::class);
            $data = CriarExemploData::from($request->validated());
            $this->service->criar($data);
            return redirect()->route('exemplos.index')->with('sucesso', 'Criado com sucesso.');
        });
    }

    public function show(Exemplo $exemplo)
    {
        return $this->tratarErros(function () use ($exemplo) {
            $this->authorize('view', $exemplo);
            return view('exemplo::show', compact('exemplo'));
        });
    }

    public function edit(Exemplo $exemplo)
    {
        return $this->tratarErros(function () use ($exemplo) {
            $this->authorize('update', $exemplo);
            return view('exemplo::edit', compact('exemplo'));
        });
    }

    public function update(AtualizarExemploRequest $request, Exemplo $exemplo)
    {
        return $this->tratarErros(function () use ($request, $exemplo) {
            $this->authorize('update', $exemplo);
            $data = AtualizarExemploData::from($request->validated());
            $this->service->atualizar($exemplo, $data);
            return redirect()->route('exemplos.index')->with('sucesso', 'Atualizado com sucesso.');
        });
    }

    public function destroy(Exemplo $exemplo)
    {
        return $this->tratarErros(function () use ($exemplo) {
            $this->authorize('delete', $exemplo);
            $this->service->excluir($exemplo);
            return redirect()->route('exemplos.index')->with('sucesso', 'Excluido com sucesso.');
        });
    }
}
```

## Pontos importantes

- `$this->authorize()` em TODOS os metodos
- `TratamentoErros` trata NegocioException, ValidationException, AuthorizationException
- DTOs criados com `::from($request->validated())`
- Flash messages: chave `sucesso` ou `erro`
- Views referenciadas como `modulo::view` (registradas pelo ModuleServiceProvider)

## Service (padrao)

```php
class ExemploService
{
    public function listar()
    {
        return Exemplo::latest()->get(); // ou paginate()
    }

    public function buscar(int $id): Exemplo
    {
        return Exemplo::findOrFail($id);
    }

    public function criar(CriarExemploData $data): Exemplo
    {
        return Exemplo::create($data->toArray());
    }

    public function atualizar(Exemplo $exemplo, AtualizarExemploData $data): Exemplo
    {
        $exemplo->update(array_filter($data->toArray(), fn ($v) => $v !== null));
        return $exemplo;
    }

    public function excluir(Exemplo $exemplo): void
    {
        $exemplo->delete(); // soft delete
    }
}
```

## Observacoes

- `rede_id` e `empresa_id` sao auto-atribuidos pelas traits (nao precisa passar no create)
- Views usam `@extends('layouts.app')`
- Verbos de rota em portugues: `/exemplos/novo` (create), `/exemplos/{id}/editar` (edit)
- Flash messages exibidas no layout automaticamente
