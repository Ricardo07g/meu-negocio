# Modulo: Papel

Gestao de papeis (roles) usando Spatie Permission. Nao tem model proprio — usa Role do Spatie.

## Localizacao

`app/Modules/Papel/`

## Camadas

| Camada | Arquivos |
|--------|----------|
| Controllers | PapelController.php |
| Policies | PapelPolicy.php |
| Views | index, create, edit |

## Funcionamento

- Usa `Spatie\Permission\Models\Role` diretamente
- PapelPolicy registrada no AppServiceProvider para autorizar operacoes
- CRUD de papeis com atribuicao de permissoes
- Verbos de rota: `papeis` com parametro `papel`

## Papeis padrao (PapelEnum)

Admin, Gerente, Profissional, Recepcao, Financeiro, Estoque, Visualizador

Nota: "Dono" nao esta no enum — e o criador da rede, definido no registro.

## Rotas

```
GET    /papeis           → index
GET    /papeis/novo      → create
POST   /papeis           → store
GET    /papeis/{papel}/editar → edit
PUT    /papeis/{papel}   → update
DELETE /papeis/{papel}   → destroy
```
