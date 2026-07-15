<?php

declare(strict_types=1);

namespace App\Modules\Conta\Controllers;

use App\Enums\TipoConta;
use App\Http\Controllers\Controller;
use App\Modules\Conta\DTOs\ContaData;
use App\Modules\Conta\Models\Conta;
use App\Modules\Conta\Requests\SalvarContaRequest;
use App\Modules\Conta\Services\ContaService;
use App\Support\ContextoEmpresa;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class ContaController extends Controller
{
    use TratamentoErros;

    public function __construct(private ContaService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Conta::class);

            $filtros = $request->only(['q', 'ativo', 'tipo']);

            return view('conta::index', [
                'contas' => $this->service->listar($filtros),
                'filtros' => $filtros,
                'tipos' => TipoConta::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar contas');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', Conta::class);

            return view('conta::create', ['tipos' => TipoConta::cases()]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de conta');
        }
    }

    public function store(SalvarContaRequest $request): RedirectResponse
    {
        try {
            $this->authorize('create', Conta::class);

            $empresaId = ContextoEmpresa::resolver() ?? $request->user()->empresa_id;
            if (! $empresaId) {
                return redirect()->back()->withInput()
                    ->with('erro', 'Selecione uma empresa no topo para cadastrar a conta.');
            }

            $this->service->criar(ContaData::from($request->validated()), (int) $empresaId);

            return redirect()->route('contas.index')->with('sucesso', 'Conta criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar conta');
        }
    }

    public function edit(Conta $conta): View|RedirectResponse
    {
        try {
            $this->authorize('update', $conta);

            return view('conta::edit', [
                'conta' => $conta,
                'tipos' => TipoConta::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de conta');
        }
    }

    public function update(SalvarContaRequest $request, Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('update', $conta);
            $this->service->atualizar($conta, ContaData::from($request->validated()));

            return redirect()->route('contas.index')->with('sucesso', 'Conta atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar conta');
        }
    }

    public function destroy(Conta $conta): RedirectResponse
    {
        try {
            $this->authorize('delete', $conta);
            $this->service->excluir($conta);

            return redirect()->route('contas.index')->with('sucesso', 'Conta excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir conta');
        }
    }
}
