<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Controllers;

use App\Enums\TipoFormaPagamento;
use App\Http\Controllers\Controller;
use App\Modules\FormaPagamento\DTOs\FormaPagamentoData;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\FormaPagamento\Requests\SalvarFormaPagamentoRequest;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Traits\TratamentoErros;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class FormaPagamentoController extends Controller
{
    use TratamentoErros;

    public function __construct(private FormaPagamentoService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', FormaPagamento::class);

            $filtros = $request->only(['q', 'ativo', 'tipo']);
            $formas = $this->service->listar($filtros);

            return view('formapagamento::index', [
                'formas' => $formas,
                'filtros' => $filtros,
                'tipos' => TipoFormaPagamento::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar formas de pagamento');
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            $this->authorize('create', FormaPagamento::class);

            return view('formapagamento::create', [
                'tipos' => TipoFormaPagamento::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar formulário de forma de pagamento');
        }
    }

    public function store(SalvarFormaPagamentoRequest $request): RedirectResponse
    {
        try {
            $this->authorize('create', FormaPagamento::class);
            $dados = FormaPagamentoData::from($request->validated());
            $this->service->criar($dados, $request->input('taxas', []));

            return redirect()->route('formas-pagamento.index')->with('sucesso', 'Forma de pagamento criada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao criar forma de pagamento');
        }
    }

    public function edit(FormaPagamento $formas_pagamento): View|RedirectResponse
    {
        try {
            $this->authorize('update', $formas_pagamento);
            $formas_pagamento->load('taxas');

            return view('formapagamento::edit', [
                'forma' => $formas_pagamento,
                'tipos' => TipoFormaPagamento::cases(),
            ]);
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de forma de pagamento');
        }
    }

    public function update(SalvarFormaPagamentoRequest $request, FormaPagamento $formas_pagamento): RedirectResponse
    {
        try {
            $this->authorize('update', $formas_pagamento);
            $dados = FormaPagamentoData::from($request->validated());
            $this->service->atualizar($formas_pagamento, $dados, $request->input('taxas', []));

            return redirect()->route('formas-pagamento.index')->with('sucesso', 'Forma de pagamento atualizada com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar forma de pagamento');
        }
    }

    public function destroy(FormaPagamento $formas_pagamento): RedirectResponse
    {
        try {
            $this->authorize('delete', $formas_pagamento);
            $this->service->excluir($formas_pagamento);

            return redirect()->route('formas-pagamento.index')->with('sucesso', 'Forma de pagamento excluída com sucesso.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao excluir forma de pagamento');
        }
    }
}
