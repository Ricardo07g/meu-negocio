<?php

namespace App\Http\Controllers;

use App\Enums\StatusAgendamento;
use App\Http\Requests\Agendamento\AtualizarAgendamentoRequest;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Services\AgendamentoService;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    use TratamentoErros;

    public function __construct(private AgendamentoService $service)
    {}

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);

            $data = $request->has('data')
                ? Carbon::parse($request->data)
                : Carbon::today();

            $agendamentos = $this->service->listarPorData($data);

            return view('agenda.index', compact('agendamentos', 'data'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar agenda');
        }
    }

    public function show(Request $request, Agendamento $agendamento): JsonResponse|View|RedirectResponse
    {
        try {
            $this->authorize('view', $agendamento);
            $agendamento->load(['cliente', 'servico', 'profissional.usuario', 'pagamento', 'vendaPacote']);

            if ($request->ajax()) {
                return response()->json([
                    'cliente' => $agendamento->cliente->nome ?? '-',
                    'servico' => $agendamento->servico->nome ?? '-',
                    'profissional' => $agendamento->profissional->usuario->nome ?? '-',
                    'data' => $agendamento->inicio->format('d/m/Y'),
                    'horario' => $agendamento->inicio->format('H:i') . ' - ' . $agendamento->fim->format('H:i'),
                    'status' => $agendamento->status->value,
                    'observacoes' => $agendamento->observacoes ?? '-',
                    'pacote_id' => $agendamento->venda_pacote_id,
                    'edit_url' => route('agenda.edit', $agendamento),
                ]);
            }

            return view('agenda.show', compact('agendamento'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao exibir agendamento');
        }
    }

    public function edit(Agendamento $agendamento): View|RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $clientes = Cliente::all();
            $servicos = Servico::all();
            $profissionais = Profissional::with('usuario')->get();

            return view('agenda.edit', compact('agendamento', 'clientes', 'servicos', 'profissionais'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de agendamento');
        }
    }

    public function update(AtualizarAgendamentoRequest $request, Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->atualizar($agendamento, \App\DTO\Agendamento\AtualizarAgendamentoData::from($request->validated()));

            return redirect()->route('agenda.index')->with('sucesso', 'Agendamento atualizado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar agendamento');
        }
    }

    public function confirmar(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->confirmar($agendamento);

            return redirect()->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento confirmado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao confirmar agendamento');
        }
    }

    public function finalizar(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->finalizar($agendamento);

            return redirect()->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento finalizado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao finalizar agendamento');
        }
    }

    public function cancelar(Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('cancel', $agendamento);
            $this->service->cancelar($agendamento);

            return redirect()->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento cancelado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao cancelar agendamento');
        }
    }
}
