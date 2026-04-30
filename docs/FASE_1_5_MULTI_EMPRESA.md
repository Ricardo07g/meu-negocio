# Fase 1.5 — Multi-empresa (Rede com N empresas)

> Documento técnico de execução. Audiência: agente dev `laravel-senior-architect`. Linguagem: PT-BR.

---

## Contexto

A Fase 1 de fechamento entregou o portfólio funcional, mas a auditoria de multi-empresa revelou que o sistema **não tratava corretamente** o cenário "uma Rede com N Empresas":

- Cliente, Serviço, Produto não tinham `empresa_id` — Ricardo decidiu que **isso é correto** (são catálogo de rede; pivot de preço por empresa fica para um futuro).
- **Usuário não-admin tinha vínculo com 1 única empresa** (`usuarios.empresa_id`). Decisão de Ricardo: **deve ser N:N** (não-admin pode acessar várias empresas).
- **Admin** já vê tudo automaticamente via `EmpresaTrait` + `hasRole('Admin')`. Mantido.
- **Sem UI de troca/seleção de empresa no header.** Decisão: header tem **multi-select com checkboxes**. Usuário marca/desmarca quais empresas quer visualizar simultaneamente (afeta listagens, dashboard, contadores).
- **Cadastro/edição de usuário não-admin sem nenhuma empresa atribuída:** bloquear salvar (validação obriga ≥1 empresa).

## Decisões de produto consolidadas

1. **Cliente, Serviço, Produto = por rede** (catálogo único; sem `empresa_id`).
2. **Usuário ↔ Empresa = N:N via pivot `empresa_usuario`.**
3. **`usuarios.empresa_id` é mantido como "empresa default ao logar"** (não dropar — minimiza impacto em ~15 arquivos que já usam essa coluna). A pivot é a fonte de verdade de "empresas que pode acessar".
4. **Header tem multi-select de empresas atuais** (checkboxes). Estado em `session('empresas_atuais', [...])`.
   - Admin: lista todas as empresas da rede.
   - Não-admin: lista apenas empresas do pivot.
5. **Não-admin sem ≥1 empresa atribuída no cadastro = `validation error`** (não salva).
6. **Operações que exigem 1 empresa específica** (criar agendamento, operar caixa, criar venda) usam um sub-seletor no form/modal quando há múltiplas selecionadas; default = primeira da sessão.
7. **Caixa diário** exige exatamente 1 empresa selecionada — se múltiplas, mostra mensagem orientando o usuário a reduzir a seleção para operar.
8. **Dashboard:**
   - Cards "Total Clientes" / "Serviços Ativos" continuam contando a rede inteira (catálogo é da rede). Adicionar legenda "(rede)".
   - Cards transacionais (agendamentos hoje, receita mês, contas a receber, caixa) filtram pelas empresas selecionadas. Adicionar legenda "(empresas atuais)".

## Backlog priorizado

### ME-001 — Migration: tabela pivot `empresa_usuario` (S)
- Migration nova: `database/migrations/2026_04_25_XXXXXX_create_empresa_usuario_table.php`
- Colunas: `id`, `rede_id` (FK rede, índice), `empresa_id` (FK empresa, índice), `usuario_id` (FK usuarios, índice), `created_at`, `updated_at`.
- Índice composto único: `(empresa_id, usuario_id)`.
- `cascadeOnDelete` em `usuario_id` e `empresa_id`.

### ME-002 — Models: relação N:N Usuario ↔ Empresa (S)
- `app/Modules/Usuario/Models/Usuario.php`: adicionar relação
  ```php
  public function empresas(): BelongsToMany {
      return $this->belongsToMany(Empresa::class, 'empresa_usuario')->withTimestamps();
  }
  ```
- `app/Modules/Tenant/Models/Empresa.php`: adicionar relação inversa `usuarios()`.
- **NÃO remover** `usuarios.empresa_id` — fica como default.

### ME-003 — Form de cadastro/edição de Usuário (M)
- `app/Modules/Usuario/Views/create.blade.php` e `edit.blade.php`: substituir o select único de empresa por uma **lista de checkboxes** "Empresas com acesso" (todas da rede).
- Para usuário com perfil Admin: ocultar (mostrar mensagem "Admin acessa todas as empresas") ou desabilitar.
- Layout: usar grid Bootstrap (col-md-4 por checkbox), buscar padrão visual no Duralux Admin (componentes de checkbox cards).
- `SalvarUsuarioRequest`: adicionar `empresas` array. Para edição, popular checkboxes com pivot atual.

### ME-004 — Validação obrigatória no SalvarUsuarioRequest (S)
- Regra: se papel ≠ Admin → `empresas` `required`, `array`, `min:1`, cada item `exists:empresas,id` e dentro da rede do usuário criando.
- Admin: `empresas` opcional (vê tudo automaticamente).
- Mensagem: "Selecione ao menos uma empresa para o usuário".

### ME-005 — Sincronizar pivot no UsuarioService/CriarUsuarioAction (S)
- Após `Usuario::create()` ou `update()`: `$usuario->empresas()->sync($data->empresas ?? [])`.
- DTO `UsuarioData`: adicionar campo `?array $empresas = null`.

### ME-006 — `EmpresaTrait` lê da sessão (M) [PONTO DE RISCO ALTO]
- Trocar a lógica do scope:
  - Atualmente: `WHERE empresa_id = $usuario->empresa_id` (não Admin).
  - Novo: `WHERE empresa_id IN (session('empresas_atuais', [...]))`.
- Comportamento:
  - Admin com nada na sessão (login fresh) → popular com **todas as empresas da rede**.
  - Não-admin com nada na sessão → popular com **todas as empresas do pivot do usuário**.
  - Admin/Não-admin com sessão preenchida → respeita a seleção (filtro `IN`).
- Boot ao criar entidade: se a sessão tem 1 empresa selecionada, atribuir automaticamente; se tem >1, exigir que o caller passe o `empresa_id` explicitamente.
- Documentar bem com comentário no código.

### ME-007 — Middleware `VerificarEmpresa` ajustado (S)
- Validar:
  1. Se `session('empresas_atuais')` não existe → popular default (Admin = rede; não-admin = pivot).
  2. Se existe → cada ID precisa estar em `usuario->empresas` (pivot) OU usuário ser Admin.
  3. Se array vazio → erro 403 ou redirect com mensagem.
- Não-admin sem nenhuma empresa no pivot (caso degenerado): bloquear acesso e exibir mensagem orientando contato com admin.

### ME-008 — Policies ajustadas (8 arquivos) (M)
Substituir em todos:
- `$usuario->empresa_id === $entidade->empresa_id`
  por
- `$usuario->empresas->pluck('id')->contains($entidade->empresa_id)` (ou método helper `$usuario->podeAcessarEmpresa($id)`).

Arquivos:
- `app/Modules/Despesa/Policies/DespesaPolicy.php`
- `app/Modules/Tenant/Policies/EmpresaPolicy.php`
- `app/Modules/Agenda/Policies/AgendamentoPolicy.php`
- `app/Modules/Pagamento/Policies/PagamentoPolicy.php`
- `app/Modules/Usuario/Policies/UsuarioPolicy.php`
- `app/Modules/Venda/Policies/VendaPacotePolicy.php`
- `app/Modules/Caixa/Policies/CaixaPolicy.php`
- + qualquer outra Policy com mesma lógica (verificar `VendaProdutoPolicy` se existir, `PerfilAcessoPolicy`).

Sugestão: criar método helper no `Usuario`:
```php
public function podeAcessarEmpresa(int $empresaId): bool {
    return $this->hasRole('Admin') || $this->empresas->pluck('id')->contains($empresaId);
}
```

### ME-009 — Seletor de empresa no header (multi-select com checkboxes) (M)
- `resources/views/layouts/app.blade.php`: adicionar dropdown no header (canto superior direito, antes do user menu).
- Visual:
  - Botão fechado: "Empresas: 3 selecionadas ▼" (ou "Todas (5)" / "Filial Centro" se 1 só).
  - Aberto: lista de checkboxes com nome da empresa, opção "Marcar todas / Desmarcar todas".
  - Submit assíncrono via fetch para `POST /empresas-atuais`.
- Endpoint: `EmpresaAtualController@atualizar` → atualiza `session('empresas_atuais', $request->ids)` → retorna 204 + reload da página atual.
- Rota: `routes/web.php`: `Route::post('/empresas-atuais', [EmpresaAtualController::class, 'atualizar'])->name('empresas-atuais.atualizar');`
- Buscar padrão visual no Duralux Admin para dropdowns.

### ME-010 — Telas de operação ajustadas para múltiplas selecionadas (M)
- **Caixa Diário** (`app/Modules/Caixa/Views/index.blade.php` ou similar): se `count(session('empresas_atuais')) !== 1`, exibir card vazio com mensagem "Selecione exatamente 1 empresa no header para operar o caixa".
- **Criar Agendamento** (`app/Modules/Agenda/Views/`): se múltiplas selecionadas, exigir campo `empresa_id` no form (sub-seletor); se 1, automatic.
- **Criar Venda** (`app/Modules/Venda/Views/create.blade.php`): mesmo tratamento.
- **Criar Pagamento manual** (se houver fluxo direto): mesmo.
- **Criar Despesa**: mesmo.

### ME-011 — Dashboard ajustado (S)
- `app/Modules/Dashboard/Services/DashboardService.php`:
  - `totalClientes()` e `servicosAtivos()`: continuam como estão (rede). Adicionar comentário "intencionalmente por rede".
  - Cards transacionais já passam pelo `EmpresaTrait` automaticamente — confirmar que respeitam o `IN` novo.
- `app/Modules/Dashboard/Views/dashboard.blade.php`:
  - Adicionar legenda no card "Total Clientes" → "(rede)".
  - Adicionar legenda nos cards transacionais → "(empresas atuais)".

### ME-012 — DesenvolvimentoSeeder ajustado (S)
- Após criar usuários, popular pivot:
  - Admin: pivot vazio (não precisa, vê tudo via Role).
  - Demais usuários demo: distribuir entre empresas (ex.: usuário 1 → empresas 1,2; usuário 2 → empresa 1; usuário 3 → empresas 1,2,3).
- Validar que `php artisan migrate:fresh --seed && php artisan db:seed --class=DesenvolvimentoSeeder` continua rodando.

### ME-013 — Atualizar `CLAUDE.md` (S)
- Adicionar seção "Multi-empresa":
  - Regra: Cliente/Serviço/Produto/Categorias = rede; Agendamento/Venda/Pagamento/Despesa/Caixa/Estoque = empresa.
  - Modelo: `usuarios.empresa_id` (default) + pivot `empresa_usuario` (acesso) + `session('empresas_atuais')` (atual).
  - Admin tem acesso automático a todas as empresas.

## Ordem de execução

Respeitando dependências:

1. **ME-001** (pivot migration) — fundação
2. **ME-002** (models N:N) — depende de 1
3. **ME-005** (sync no service/action) — depende de 2
4. **ME-003** (form com checkboxes) — depende de 2
5. **ME-004** (validação Request) — depende de 2
6. **ME-008** (policies usando pivot) — depende de 2
7. **ME-006** (EmpresaTrait com sessão) — depende de 2 [RISCO ALTO, testar bastante]
8. **ME-007** (middleware) — depende de 6
9. **ME-009** (seletor header + endpoint) — depende de 6, 7
10. **ME-010** (telas de operação) — depende de 9
11. **ME-011** (dashboard ajustes) — depende de 6
12. **ME-012** (seeder) — depende de 1, 2
13. **ME-013** (docs) — último

Cada item = 1 commit (formato `feat({modulo}): ...` / `refactor({modulo}): ...` / `fix(...)` / `chore: ...` / `docs: ...`, com referência `(ME-XXX)` no corpo).

## Verificação end-to-end

```bash
# Reset completo
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=DesenvolvimentoSeeder
docker compose exec app php artisan permission:cache-reset
```

Validar manualmente:

- [ ] Login como Admin: header mostra todas as empresas marcadas; pode marcar/desmarcar; listagens respeitam.
- [ ] Login como não-admin com 1 empresa no pivot: header mostra apenas essa empresa, marcada; sem opção "todas".
- [ ] Login como não-admin com 3 empresas no pivot: header mostra as 3, todas marcadas por default; pode marcar/desmarcar.
- [ ] Cadastrar novo não-admin sem marcar nenhuma empresa: validation error "Selecione ao menos uma empresa".
- [ ] Cadastrar novo Admin: campo de empresas oculto/desabilitado; salva normalmente.
- [ ] Editar usuário: checkboxes pré-marcados conforme pivot atual.
- [ ] Caixa Diário com 1 empresa selecionada: opera normalmente.
- [ ] Caixa Diário com 2+ empresas selecionadas: mensagem orientando reduzir seleção.
- [ ] Criar Agendamento com 2+ empresas: form mostra select adicional "Empresa" (default = primeira).
- [ ] Dashboard: card "Clientes (rede)" mostra total da rede; card "Receita do mês (empresas atuais)" filtra pelas selecionadas.
- [ ] Multi-tenant: usuário rede A não vê empresa/dado da rede B (RedeTrait continua aplicado).
- [ ] Trocar seleção no header reflete imediatamente em listas e dashboard.

```bash
# Tests + Lint
docker compose exec app composer test       # baseline 1 falha mantida
docker compose exec app vendor/bin/pint --test  # verificar arquivos tocados
```

## Restrições

1. **Não dropar `usuarios.empresa_id`** — manter como default. Pivot é fonte de verdade.
2. **Não tocar em Cliente/Serviço/Produto** — decisão de produto: ficam por rede.
3. **Não introduzir dependências novas** sem aprovação.
4. **Cada item = 1 commit.** Para itens grandes (M+), considerar sub-commits internos.
5. **Após ME-006 (trait)** rodar suite manual completa antes de seguir — é o ponto de mais risco.
6. **Respeitar 100% padrões CLAUDE.md** (PT-BR, BaseModel, Requests/DTOs unificados, _form, padrão Duralux).

## Esforço estimado

13 itens, ~16 pontos relativos (S=1, M=3). **Realisticamente 2-3 dias de trabalho focado** do `laravel-senior-architect`.

---

**Mantenedor:** Tech Product Owner.
**Data:** 2026-04-25.
**Status:** Aguardando aprovação do Ricardo para execução.
