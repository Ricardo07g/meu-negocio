<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Controllers;

use App\Enums\{MotivoSemCobranca, StatusAgendamento};
use App\Exceptions\{ForaDoExpedienteException, NegocioException};
use App\Http\Controllers\Controller;
use App\Modules\Agenda\Actions\{CriarAgendamentoAction, VerificarDisponibilidadeAction};
use App\Modules\Agenda\DTOs\AgendamentoData;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Agenda\Requests\SalvarAgendamentoRequest;
use App\Modules\Agenda\Services\AgendamentoService;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Servico\Models\Servico;
use App\Modules\Tenant\Services\ExpedienteService;
use App\Modules\Usuario\Models\Usuario;
use App\Support\Agenda\RegrasDeVinculo;
use App\Support\ContextoEmpresa;
use App\Traits\TratamentoErros;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class AgendaController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private AgendamentoService $service,
        private ExpedienteService $expediente,
    ) {}

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

            $empresaId = ContextoEmpresa::resolver();
            $atendentesLista = ($empresaId
                ? Usuario::atendentesDaEmpresa($empresaId)
                : Usuario::where('atende', true))
                ->orderBy('nome')->get();
            $calendars = $atendentesLista->values()->map(fn ($u, $i) => [
                'id' => (string) $u->id,
                'name' => $u->nome,
                'backgroundColor' => $this->coresAtendente[$i % count($this->coresAtendente)],
                'borderColor' => $this->coresAtendente[$i % count($this->coresAtendente)],
            ]);

            $eventos = $agendamentos->map(function (Agendamento $ag) {
                $cancelado = $ag->status === StatusAgendamento::Cancelado;
                $finalizado = $ag->status === StatusAgendamento::Finalizado;
                $situacao = $ag->situacaoFinanceira();

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
                        // O que a recepcao precisa ver antes de liberar o cliente.
                        'situacao' => $situacao->value,
                        'situacao_label' => $situacao->label(),
                        'situacao_cor' => $situacao->cor(),
                        'motivo_sem_cobranca' => $ag->motivo_sem_cobranca?->label(),
                        'fora_expediente' => (bool) $ag->fora_expediente,
                        'valor' => (float) ($ag->servico->valor ?? 0),
                        'confirmar_url' => route('agenda.confirmar', $ag),
                        'finalizar_url' => route('agenda.finalizar', $ag),
                        'cobrar_url' => route('vendas.create', ['agendamento' => $ag->id]),
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

            // ME-010 v3: empresa vem do contexto da listagem ou do header.
            // Form NAO envia empresa_id — o EmpresaTrait resolve via session.
            $empresasAtuais = (array) session('empresas_atuais', []);

            $dados = $request->validate([
                'empresa_id' => array_filter([
                    'nullable',
                    'integer',
                    $empresasAtuais !== [] ? 'in:'.implode(',', $empresasAtuais) : null,
                ]),
                'inicio' => 'required|date',
                'fim' => 'nullable|date|after:inicio',
            ] + RegrasDeVinculo::paraAgendamento(
                (int) $request->user()->rede_id,
                ContextoEmpresa::resolver(),
            ));

            $agendamento = $action->executar(
                AgendamentoData::from([
                    'empresa_id' => isset($dados['empresa_id']) ? (int) $dados['empresa_id'] : null,
                    'cliente_id' => (int) $dados['cliente_id'],
                    'servico_id' => (int) $dados['servico_id'],
                    'atendente_id' => (int) $dados['atendente_id'],
                    'inicio' => Carbon::parse($dados['inicio']),
                    'fim' => ! empty($dados['fim']) ? Carbon::parse($dados['fim']) : null,
                ]),
                $this->encaixeAutorizado($request),
            );

            return response()->json(['id' => $agendamento->id], 201);
        } catch (\Throwable $e) {
            return $this->erroJson($e);
        }
    }

    /**
     * Move inicio/fim de um atendimento.
     *
     * Passa pelo mesmo validador da criacao: antes daqui nao sair nada, o
     * drag-and-drop do calendario empilhava dois clientes no mesmo atendente —
     * o `reagendar` nao revalidava conflito nenhum.
     */
    public function reagendar(Request $request, Agendamento $agendamento, VerificarDisponibilidadeAction $verificar): JsonResponse
    {
        try {
            $this->authorize('update', $agendamento);

            $dados = $request->validate([
                'inicio' => 'required|date',
                'fim' => 'required|date|after:inicio',
            ]);

            $inicio = Carbon::parse($dados['inicio']);
            $fim = Carbon::parse($dados['fim']);

            $foraExpediente = $verificar->executar(
                empresaId: (int) $agendamento->empresa_id,
                atendenteId: (int) $agendamento->atendente_id,
                inicio: $inicio,
                fim: $fim,
                ignorarId: $agendamento->id,
                forcarHorario: $this->encaixeAutorizado($request),
            );

            $agendamento->update([
                'inicio' => $inicio,
                'fim' => $fim,
                'fora_expediente' => $foraExpediente,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->erroJson($e);
        }
    }

    /**
     * O usuario pediu encaixe fora do expediente — e pode?
     *
     * Pedir sem permissao e 403, nao um "ignora e segue": silenciar o pedido
     * faria a tela mostrar "agendado" para quem nao tinha autoridade.
     */
    private function encaixeAutorizado(Request $request): bool
    {
        if (! $request->boolean('forcar_horario')) {
            return false;
        }

        $this->authorize('forcarHorario', Agendamento::class);

        return true;
    }

    /**
     * Resposta JSON de erro das rotas AJAX da agenda.
     *
     * `fora_expediente` viaja com um `codigo` estavel porque a tela precisa
     * distinguir "nao pode" de "quer mesmo?" — e texto de mensagem nao e
     * contrato.
     */
    private function erroJson(\Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Dados inválidos',
            ], 422);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        if ($e instanceof ForaDoExpedienteException) {
            return response()->json([
                'message' => $e->getMessage(),
                'codigo' => ForaDoExpedienteException::CODIGO,
            ], 422);
        }

        return response()->json(['message' => $e->getMessage()], $this->statusDoErro($e));
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $this->authorize('viewAny', Agendamento::class);

            $empresaId = ContextoEmpresa::resolver();
            $atendentes = ($empresaId
                ? Usuario::atendentesDaEmpresa($empresaId)
                : Usuario::where('atende', true))
                ->orderBy('nome')->get();
            $cores = $this->coresAtendente;

            // Desfechos possiveis de "finalizar sem cobrar" — o calendario monta
            // o modal com eles em vez de repetir a lista no JS.
            $motivosSemCobranca = collect(MotivoSemCobranca::cases())
                ->map(fn (MotivoSemCobranca $m) => ['valor' => $m->value, 'label' => $m->label()])
                ->values();

            // A grade do calendario passa a desenhar o expediente configurado,
            // no lugar do 8–21 que estava fixo no JS.
            $empresaDaAgenda = $empresaId ?? (int) $request->user()->empresa_id;
            [$horaInicial, $horaFinal] = $this->expediente->janelaDoCalendario((int) $empresaDaAgenda);
            $resumoExpediente = $this->expediente->resumoPorDia((int) $empresaDaAgenda);
            $podeForcarHorario = $request->user()->can('agendamento.forcar_horario');

            return view('agenda::index', compact(
                'atendentes', 'cores', 'motivosSemCobranca',
                'horaInicial', 'horaFinal', 'resumoExpediente', 'podeForcarHorario',
            ));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar agenda');
        }
    }

    public function show(Request $request, Agendamento $agendamento): JsonResponse|View|RedirectResponse
    {
        try {
            $this->authorize('view', $agendamento);
            $agendamento->load(['cliente', 'servico', 'atendente', 'pagamento', 'vendaEtapas']);

            if ($request->ajax()) {
                return response()->json([
                    'cliente' => $agendamento->cliente->nome ?? '-',
                    'servico' => $agendamento->servico->nome ?? '-',
                    'atendente' => $agendamento->atendente->nome ?? '-',
                    'data' => $agendamento->inicio->format('d/m/Y'),
                    'horario' => $agendamento->inicio->format('H:i').' - '.$agendamento->fim->format('H:i'),
                    'status' => $agendamento->status->value,
                    'observacoes' => $agendamento->observacoes ?? '-',
                    'etapas_id' => $agendamento->venda_etapas_id,
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
            $empresaId = ContextoEmpresa::resolver();
            $atendentes = ($empresaId
                ? Usuario::atendentesDaEmpresa($empresaId)
                : Usuario::where('atende', true))
                ->orderBy('nome')->get();

            return view('agenda::edit', compact('agendamento', 'clientes', 'servicos', 'atendentes'));
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar edição de agendamento');
        }
    }

    public function update(SalvarAgendamentoRequest $request, Agendamento $agendamento): RedirectResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->atualizar(
                $agendamento,
                AgendamentoData::from($request->validated()),
                $this->encaixeAutorizado($request),
            );

            return redirect()
                ->route('agenda.index')
                ->with('sucesso', 'Agendamento atualizado.');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao atualizar agendamento');
        }
    }

    public function confirmar(Request $request, Agendamento $agendamento): RedirectResponse|JsonResponse
    {
        try {
            $this->authorize('update', $agendamento);
            $this->service->confirmar($agendamento);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }

            return redirect()->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento confirmado.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $this->statusDoErro($e));
            }

            return $this->tratarErro($e, 'Erro ao confirmar agendamento');
        }
    }

    /**
     * Encerra o atendimento. Sem titulo, exige o motivo de nao cobrar — o
     * desfecho financeiro e declarado, nunca implicito (a Action recusa).
     */
    public function finalizar(Request $request, Agendamento $agendamento): RedirectResponse|JsonResponse
    {
        try {
            $this->authorize('update', $agendamento);

            $dados = $request->validate([
                'motivo_sem_cobranca' => ['nullable', Rule::enum(MotivoSemCobranca::class)],
            ]);

            $this->service->finalizar(
                $agendamento,
                ! empty($dados['motivo_sem_cobranca'])
                    ? MotivoSemCobranca::from($dados['motivo_sem_cobranca'])
                    : null,
            );

            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }

            return redirect()
                ->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento finalizado.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $this->statusDoErro($e));
            }

            return $this->tratarErro($e, 'Erro ao finalizar agendamento');
        }
    }

    /**
     * Regra de negocio recusada e 422, nao 500: o cliente AJAX precisa saber a
     * diferenca entre "voce nao pode fazer isso" e "o servidor quebrou".
     */
    private function statusDoErro(\Throwable $e): int
    {
        return match (true) {
            $e instanceof AuthorizationException => 403,
            $e instanceof ValidationException, $e instanceof NegocioException => 422,
            default => 500,
        };
    }

    public function cancelar(Request $request, Agendamento $agendamento): RedirectResponse|JsonResponse
    {
        try {
            $this->authorize('cancel', $agendamento);
            $this->service->cancelar($agendamento);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }

            return redirect()
                ->route('agenda.index', ['data' => $agendamento->inicio->format('Y-m-d')])
                ->with('sucesso', 'Agendamento cancelado.');
        } catch (\Throwable $e) {
            // Cancelar agora estorna: caixa fechado na data do recebimento vira
            // uma recusa com instrucao ("reabra o caixa"), nao um 500 mudo.
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $this->statusDoErro($e));
            }

            return $this->tratarErro($e, 'Erro ao cancelar agendamento');
        }
    }
}
