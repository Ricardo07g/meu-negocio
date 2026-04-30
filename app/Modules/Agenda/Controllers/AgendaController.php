<?php

namespace App\Modules\Agenda\Controllers;

use App\Enums\StatusAgendamento;
use App\Http\Controllers\Controller;
use App\Modules\Agenda\Actions\CriarAgendamentoAction;
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Agenda\Requests\SalvarAgendamentoRequest;
use App\Modules\Agenda\Services\AgendamentoService;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AgendaController extends Controller
{
    use TratamentoErros;

    public function __construct(private AgendamentoService $service) {}

    private array $coresAtendente = [
        '#3454d1', '#25b865', '#e49e3d', '#d13b4c', '#17a2b8',
        '#5856d6', '#3dc7be', '#475e77', '#f59e0b', '#8b5cf6',
    ];

    public function json(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);

            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);

            $agendamentos = $this->service->listarPorPeriodo($start, $end);

            $atendentesLista = Usuario::where('atende', true)->orderBy('nome')->get();
            $calendars = $atendentesLista->values()->map(fn ($u, $i) => [
                'id' => (string) $u->id,
                'name' => $u->nome,
                'backgroundColor' => $this->coresAtendente[$i % count($this->coresAtendente)],
                'borderColor' => $this->coresAtendente[$i % count($this->coresAtendente)],
            ]);

            $eventos = $agendamentos->map(function ($ag) {
                $cancelado = $ag->status === StatusAgendamento::Cancelado;
                $finalizado = $ag->status === StatusAgendamento::Finalizado;

                return [
                    'id' => (string) $ag->id,
                    'calendarId' => (string) $ag->atendente_id,
                    'title' => ($ag->cliente->nome ?? '-').' — '.($ag->servico->nome ?? '-'),
                    'start' => $ag->inicio->format('Y-m-d\TH:i:s'),
                    'end' => $ag->fim->format('Y-m-d\TH:i:s'),
                    'category' => 'time',
                    'isReadOnly' => $cancelado || $finalizado,
                    'raw' => [
                        'status' => $ag->status->value,
                        'status_label' => $ag->status->label(),
                        'cliente' => $ag->cliente->nome ?? '-',
                        'servico' => $ag->servico->nome ?? '-',
                        'atendente' => $ag->atendente->nome ?? '-',
                        'atendente_id' => $ag->atendente_id,
                        'observacoes' => $ag->observacoes,
                        'confirmar_url' => route('agenda.confirmar', $ag),
                        'finalizar_url' => route('agenda.finalizar', $ag),
                        'cancelar_url' => route('agenda.cancelar', $ag),
                        'edit_url' => route('agenda.edit', $ag),
                    ],
                ];
            });

            return response()->json([
                'calendars' => $calendars,
                'events' => $eventos->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['calendars' => [], 'events' => [], 'message' => $e->getMessage()], 500);
        }
    }

    public function criarRapido(Request $request, CriarAgendamentoAction $action): JsonResponse
    {
        try {
            $this->authorize('create', Agendamento::class);

            // ME-010: empresa_id e exigido quando ha mais de uma empresa selecionada.
            $empresasAtuais = (array) session('empresas_atuais', []);
            $exigeEmpresa = count($empresasAtuais) > 1;

            $dados = $request->validate([
                'empresa_id' => [
                    $exigeEmpresa ? 'required' : 'nullable',
                    'integer',
                    $exigeEmpresa ? 'in:'.implode(',', $empresasAtuais) : 'nullable',
                ],
                'cliente_id' => 'required|exists:clientes,id',
                'servico_id' => 'required|exists:servicos,id',
                'atendente_id' => 'required|exists:usuarios,id',
                'inicio' => 'required|date',
                'fim' => 'nullable|date|after:inicio',
            ]);

            $agendamento = $action->executar(AgendamentoData::from([
                'empresa_id' => isset($dados['empresa_id']) ? (int) $dados['empresa_id'] : null,
                'cliente_id' => (int) $dados['cliente_id'],
                'servico_id' => (int) $dados['servico_id'],
                'atendente_id' => (int) $dados['atendente_id'],
                'inicio' => Carbon::parse($dados['inicio']),
                'fim' => ! empty($dados['fim']) ? Carbon::parse($dados['fim']) : null,
            ]));

            return response()->json(['id' => $agendamento->id], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? 'Dados inválidos'], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function reagendar(Request $request, Agendamento $agendamento): JsonResponse
    {
        try {
            $this->authorize('update', $agendamento);

            $dados = $request->validate([
                'inicio' => 'required|date',
                'fim' => 'required|date|after:inicio',
            ]);

            $agendamento->update([
                'inicio' => Carbon::parse($dados['inicio']),
                'fim' => Carbon::parse($dados['fim']),
            ]);

            return response()->json(['ok' => true]);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? 'Dados inválidos'], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);

            $atendentes = Usuario::where('atende', true)->get();
            $cores = $this->coresAtendente;

            return view('agenda::index', compact('atendentes', 'cores'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar agenda');
        }
    }

    public function show(Request $request, Agendamento $agendamento): JsonResponse|View|RedirectResponse
    {
        try {
            $this->authorize('view', $agendamento);
            $agendamento->load(['cliente', 'servico', 'atendente', 'pagamento', 'vendaPacote']);

            if ($request->ajax()) {
                return response()->json([
                    'cliente' => $agendamento->cliente->nome ?? '-',
                    'servico' => $agendamento->servico->nome ?? '-',
                    'atendente' => $agendamento->atendente->nome ?? '-',
                    'data' => $agendamento->inicio->format('d/m/Y'),
                    'horario' => $agendamento->inicio->format('H:i').' - '.$agendamento->fim->format('H:i'),
                    'status' => $agendamento->status->value,
                    'observacoes' => $agendamento->observacoes ?? '-',
                    'pacote_id' => $agendamento->venda_pacote_id,
                    'edit_url' => route('agenda.edit', $agendamento),
                ]);
            }

            return view('agenda::show', compact('agendamento'));
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
            $atendentes = Usuario::where('atende', true)->get();

            return view('agenda::edit', compact('agendamento', 'clientes', 'servicos', 'atendentes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de agendamento');
        }
    }

    public function update(SalvarAgendamentoRequest $request, Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->atualizar($agendamento, AgendamentoData::from($request->validated()));

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
