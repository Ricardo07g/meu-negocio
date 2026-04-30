# ADR-0004 — `BaseModel` + Traits para tenancy

## Status

Aceito — abril/2026.

## Contexto

Decidido em [ADR-0001](0001-multi-tenant-single-db.md) que o isolamento multi-tenant seria por `rede_id` em coluna. Falta decidir **como aplicar o filtro** em todas as queries Eloquent sem que o dev precise lembrar de fazer isso a cada `Model::query()`.

Três caminhos foram considerados:

1. **Repository pattern**: cada módulo expõe `ClienteRepository`, `VendaRepository`, etc. que injetam o `rede_id` antes de cada query. Mantém o Eloquent puro mas adiciona uma camada inteira só para ocultar o tenant.
2. **Middleware `where()` por query** ou interceptor de query: dev escreve `Cliente::query()` e algo intercepta antes da execução. Funciona, mas a "mágica" fica em local distante do model — quebra discoverability.
3. **Eloquent Global Scopes via Trait** aplicados em uma `BaseModel`: o filtro vira parte da definição do model, óbvio na leitura, automático na execução.

Como o projeto adota Eloquent ativamente (sem repository), a opção 3 mantém o atrito baixo e o pattern visível.

## Decisão

Criamos `App\Models\BaseModel` que estende `Illuminate\Database\Eloquent\Model` e usa `RedeTrait`. **Todo model tenant-aware estende BaseModel**.

- `RedeTrait` aplica `addGlobalScope('rede', fn ($q) => $q->where('rede_id', auth()->user()->rede_id))` quando há usuário autenticado.
- `EmpresaTrait` é usado adicionalmente em modelos transacionais por empresa (Caixa, Agendamento, Venda, etc.). Filtra por `empresa_id IN (session('empresas_atuais'))`. Admin sem sessão explícita **não filtra** (vê tudo da rede).
- Modelos de catálogo (Cliente, Servico, Produto) usam apenas `RedeTrait` — são compartilhados entre todas as empresas da rede.
- Exceções pontuais (Plano, Rede, MovimentoCaixa) estendem `Model` direto. Cada uma é justificada (Plano não é tenant-aware, Rede É o tenant, MovimentoCaixa é trafegado via Caixa que já filtra).
- Usuario estende `Authenticatable` direto e usa as traits manualmente.

## Consequências

### Positivas
- **Pattern centralizado**: alterações no comportamento de tenancy mexem em um lugar (`BaseModel` + traits) e refletem em toda a aplicação.
- **Discoverability**: ler um model deixa claro o tenancy aplicado pelas traits importadas.
- **Eloquent puro**: não há repository camada para indireção. `Cliente::all()` funciona como esperado e já vem filtrado.
- **Override controlado**: quando o dev precisa **realmente** quebrar o escopo (relatório global de Admin), o caminho é explícito (`withoutGlobalScope('rede')`) e revisável em PR.

### Negativas
- **Risco de bypass acidental**: `DB::table('clientes')` ou `withoutGlobalScopes()` pulam o filtro. Mitigado por (a) preferir Eloquent sobre Query Builder, (b) testes de isolamento como guarda.
- **Acoplamento ao `auth()`**: o trait depende de um usuário autenticado. Em comandos artisan, jobs ou seeders, o dev precisa simular contexto manualmente (ou desligar o scope). Documentado nos seeders relevantes.
- **Nem todo model encaixa**: as exceções (Plano, Rede, MovimentoCaixa) viram cases especiais que precisam de justificativa quando alguém revisar.

### Neutras
- A combinação BaseModel + Trait é menos flexível que Repository, mas, dado que o projeto **abraça Eloquent** como ferramenta principal, é o trade-off correto. Repository entraria como camada extra sem ganho proporcional.
