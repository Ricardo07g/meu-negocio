@extends('layouts.app')

@section('titulo', 'Minha Assinatura - Meu Negócio')
@section('titulo-pagina', 'Minha Assinatura')
@section('breadcrumb')
    <li class="breadcrumb-item active">Minha Assinatura</li>
@endsection

@php
    $mapaBadge = [
        'paga' => ['cor' => 'success', 'rotulo' => 'Paga'],
        'em_aberto' => ['cor' => 'warning', 'rotulo' => 'Em aberto'],
        'vencida' => ['cor' => 'danger', 'rotulo' => 'Vencida'],
        'cancelada' => ['cor' => 'secondary', 'rotulo' => 'Cancelada'],
    ];
    $statusFatura = $faturaAtual?->status ?? 'em_aberto';
    $badge = $mapaBadge[$statusFatura] ?? ['cor' => 'secondary', 'rotulo' => 'Em aberto'];

    $modulosDisponiveis = [
        ['icone' => 'feather-users', 'nome' => 'Clientes', 'inclui' => true],
        ['icone' => 'feather-briefcase', 'nome' => 'Servicos', 'inclui' => true],
        ['icone' => 'feather-package', 'nome' => 'Produtos', 'inclui' => true],
        ['icone' => 'feather-calendar', 'nome' => 'Agenda', 'inclui' => true],
        ['icone' => 'feather-shopping-bag', 'nome' => 'Vendas', 'inclui' => true],
        ['icone' => 'feather-archive', 'nome' => 'Estoque', 'inclui' => (bool) $plano?->tem_estoque],
        ['icone' => 'feather-dollar-sign', 'nome' => 'Financeiro (Pagamentos / Despesas / Caixa)', 'inclui' => (bool) $plano?->tem_financeiro],
    ];

    $assentosUsados = $empresaVigente?->contarUsuarios() ?? 0;
    $assentosMax = (int) ($plano?->max_usuarios ?? 0);
    $percAssentos = $assentosMax > 0 ? min(100, round($assentosUsados / $assentosMax * 100)) : 0;
@endphp

@push('css')
<style>
    .assinatura-hero {
        background: linear-gradient(135deg, #3454d1 0%, #5e72e4 100%);
        color: #fff;
        border: none;
    }
    .assinatura-hero .plano-nome {
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    .assinatura-hero .plano-preco {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .assinatura-hero .plano-preco .moeda { font-size: 1rem; opacity: 0.85; }
    .assinatura-hero .plano-preco .periodo { font-size: 0.95rem; opacity: 0.85; font-weight: 500; }
    .assinatura-hero .plano-descricao { opacity: 0.92; max-width: 540px; }

    .recurso-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0;
        border-bottom: 1px dashed #e9ecef;
    }
    .recurso-item:last-child { border-bottom: none; }
    .recurso-item .icone-recurso {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(52, 84, 209, 0.1);
        color: #3454d1;
        flex-shrink: 0;
    }
    .recurso-item.inativo .icone-recurso { background: rgba(108, 117, 125, 0.1); color: #adb5bd; }
    .recurso-item.inativo .recurso-nome { color: #adb5bd; text-decoration: line-through; }
    .recurso-item .recurso-status { margin-left: auto; }

    .uso-card .uso-numero { font-size: 1.75rem; font-weight: 700; line-height: 1; }
    .uso-card .uso-limite { color: #6c757d; font-size: 0.9rem; }
    .uso-card .progress { height: 6px; background: #f1f3f5; }

    .fatura-card .fatura-valor { font-size: 2rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
    .fatura-card .fatura-info-row { display: flex; justify-content: space-between; align-items: center; padding: 0.55rem 0; border-bottom: 1px dashed #e9ecef; font-size: 0.9rem; }
    .fatura-card .fatura-info-row:last-child { border-bottom: none; }
    .fatura-card .fatura-info-row .label { color: #6c757d; }
    .fatura-card .fatura-info-row .value { font-weight: 600; color: #212529; }

    /* Modal de comparacao de planos */
    .plano-compara-card {
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1.25rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        background: #fff;
    }
    .plano-compara-card.atual {
        border: 2px solid #3454d1;
        box-shadow: 0 0 0 4px rgba(52, 84, 209, 0.08);
    }
    .plano-compara-card .plano-compara-nome {
        font-size: 1.15rem;
        font-weight: 700;
    }
    .plano-compara-card .plano-compara-preco {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1;
        margin-top: 0.5rem;
        font-variant-numeric: tabular-nums;
    }
    .plano-compara-card .plano-compara-preco small { font-size: 0.75rem; font-weight: 400; color: #6c757d; margin-left: 0.15rem; }
    .plano-compara-card .plano-compara-desc { font-size: 0.85rem; color: #6c757d; margin: 0.85rem 0 1rem; min-height: 3.4em; }
    .plano-compara-card ul.recursos { list-style: none; padding: 0; margin: 0; flex: 1; }
    .plano-compara-card ul.recursos li { display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; padding: 0.4rem 0; border-bottom: 1px dashed #f1f3f5; }
    .plano-compara-card ul.recursos li:last-child { border-bottom: none; }
    .plano-compara-card ul.recursos li i { font-size: 16px; flex-shrink: 0; }
    .plano-compara-card ul.recursos li.inativo { color: #adb5bd; text-decoration: line-through; }
    .plano-compara-card .plano-compara-rodape { margin-top: 1rem; }
</style>
@endpush

@section('content')
    {{-- Sem gateway de pagamento: melhor dizer isso do que simular cobranca real. --}}
    <div class="alert alert-light border d-flex align-items-center mb-3" role="alert">
        <i class="feather-info me-2"></i>
        <div>
            <strong>Cobrança simulada.</strong>
            Este ambiente não possui gateway de pagamento — as faturas são geradas internamente
            para demonstrar o modelo de licenciamento.
        </div>
    </div>

    {{-- Teste vencido: as duas saidas (renovar ou trocar de plano) ficam lado a lado. --}}
    @if ($empresaVigente?->podeRenovarTrial())
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3" role="alert">
            <i class="feather-alert-circle me-1"></i>
            <div class="flex-grow-1">
                <strong>O teste de {{ $empresaVigente->nome }} terminou em
                    {{ $empresaVigente->trial_expira_em->format('d/m/Y') }}.</strong>
                A unidade voltou para o plano Grátis. Você pode contratar o Pro ou renovar o teste
                por mais {{ \App\Modules\Tenant\Models\Empresa::DIAS_DE_TRIAL }} dias.
            </div>
            @if ($podeRenovarTeste)
                <button type="button" class="btn btn-warning btn-renovar-teste"
                    data-empresa-id="{{ $empresaVigente->id }}"
                    data-empresa-nome="{{ $empresaVigente->nome }}">
                    <i class="feather-refresh-cw me-1"></i>
                    Renovar por {{ \App\Modules\Tenant\Models\Empresa::DIAS_DE_TRIAL }} dias
                </button>
            @endif
        </div>
    @endif

    {{-- Top action: comparar planos --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted fs-13">
            Cada unidade é uma licença contratada individualmente.
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalComparaPlanos">
            <i class="feather-grid me-1"></i> Comparar planos
        </button>
    </div>

    {{-- Hero da licenca da unidade em contexto --}}
    <div class="card stretch stretch-full assinatura-hero mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge bg-white text-primary mb-2">
                        Licença de {{ $empresaVigente?->nome ?? 'sua unidade' }}
                    </span>
                    <h2 class="plano-nome mb-2">
                        {{ $plano?->nome ?? '—' }}
                        @if ($empresaVigente?->emTrial())
                            <span class="badge bg-white text-primary align-middle fs-12">
                                teste · {{ $empresaVigente->diasRestantesTrial() }}d restantes
                            </span>
                        @endif
                    </h2>
                    <p class="plano-descricao mb-0">{{ $plano?->descricao ?? 'Rede '.$rede->nome }}</p>
                </div>
                <div class="col-md-5 text-md-end mt-4 mt-md-0">
                    @if ($empresaVigente?->emTrial())
                        <div class="plano-preco">Em teste</div>
                    @elseif ($plano && $plano->preco_por_licenca > 0)
                        <div class="plano-preco">
                            <span class="moeda">R$</span>{{ number_format($plano->preco_por_licenca, 2, ',', '.') }}<span class="periodo">/mes</span>
                        </div>
                    @else
                        <div class="plano-preco">Gratuito</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Recursos inclusos --}}
        <div class="col-lg-6">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Recursos desta licença</h5>
                </div>
                <div class="card-body py-2">
                    @foreach ($modulosDisponiveis as $modulo)
                        <div class="recurso-item {{ $modulo['inclui'] ? '' : 'inativo' }}">
                            <span class="icone-recurso">
                                <i class="{{ $modulo['icone'] }} fs-16"></i>
                            </span>
                            <span class="recurso-nome fw-semibold">{{ $modulo['nome'] }}</span>
                            <span class="recurso-status">
                                @if ($modulo['inclui'])
                                    <i class="feather-check-circle text-success fs-18"></i>
                                @else
                                    <i class="feather-x-circle text-muted fs-18"></i>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Assentos da unidade em contexto --}}
        <div class="col-lg-6">
            <div class="card stretch stretch-full uso-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="text-muted fs-12 fw-semibold text-uppercase mb-1">
                                Usuarios em {{ $empresaVigente?->nome ?? 'sua unidade' }}
                            </div>
                            <div class="uso-numero">{{ $assentosUsados }}</div>
                            <div class="uso-limite">de {{ $assentosMax }}</div>
                        </div>
                        <span class="avatar avatar-md bg-soft-success text-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                            <i class="feather-users fs-18"></i>
                        </span>
                    </div>
                    @if ($assentosMax > 0)
                        <div class="progress mt-3">
                            <div class="progress-bar bg-success" style="width: {{ $percAssentos }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Licencas da rede --}}
        <div class="col-12">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-1">Licenças da rede</h5>
                        <small class="text-muted">Uma licença por unidade. Para contratar outra, fale com o suporte.</small>
                    </div>
                    <div class="text-md-end">
                        <div class="text-muted fs-12 fw-semibold text-uppercase">Total mensal</div>
                        <div class="fw-bold fs-18">R$ {{ number_format($valorMensal, 2, ',', '.') }}</div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Unidade</th>
                                    <th>Plano</th>
                                    <th>Contratada em</th>
                                    <th>Situação</th>
                                    <th class="text-end">Valor/mês</th>
                                    @if ($podeRenovarTeste)
                                        <th class="text-end">Ações</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($licencas as $licenca)
                                    <tr>
                                        <td class="fw-semibold">{{ $licenca->nome }}</td>
                                        <td>{{ $licenca->plano->nome }}</td>
                                        <td>{{ $licenca->created_at?->format('d/m/Y') ?? '-' }}</td>
                                        <td>
                                            @if ($licenca->emTrial())
                                                <span class="badge bg-info">Teste · {{ $licenca->diasRestantesTrial() }}d</span>
                                            @elseif ($licenca->trialVencido())
                                                <span class="badge bg-warning">Teste encerrado</span>
                                            @else
                                                <span class="badge bg-success">Ativa</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($licenca->emTrial())
                                                <span class="text-muted">—</span>
                                            @else
                                                R$ {{ number_format($licenca->plano->preco_por_licenca, 2, ',', '.') }}
                                            @endif
                                        </td>
                                        @if ($podeRenovarTeste)
                                            <td class="text-end">
                                                @if ($licenca->podeRenovarTrial())
                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-renovar-teste"
                                                        data-empresa-id="{{ $licenca->id }}"
                                                        data-empresa-nome="{{ $licenca->nome }}">
                                                        <i class="feather-refresh-cw me-1"></i> Renovar teste
                                                    </button>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fatura atual --}}
        <div class="col-12" id="fatura-atual">
            <div class="card stretch stretch-full fatura-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Fatura atual</h5>
                    @if ($valorMensal > 0)
                        <span class="badge bg-soft-{{ $badge['cor'] }} text-{{ $badge['cor'] }} border border-{{ $badge['cor'] }}-subtle">
                            {{ $badge['rotulo'] }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($valorMensal <= 0)
                        <div class="text-muted">
                            <strong class="text-body">Sem cobrança.</strong>
                            Nenhuma licença cobrável nesta rede no momento — unidades em teste gratuito
                            ou no plano Grátis não geram fatura.
                        </div>
                    @else
                        @php
                            $valorAtual = (float) ($faturaAtual?->valor ?? $valorMensal);
                            $referenciaAtualLabel = $faturaAtual
                                ? ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $faturaAtual->referencia)->locale('pt_BR')->isoFormat('MMMM/YYYY'))
                                : ucfirst(now()->locale('pt_BR')->isoFormat('MMMM/YYYY'));
                            $vencimentoAtual = $faturaAtual?->vencimento;
                        @endphp
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <div class="text-muted fs-12 fw-semibold text-uppercase mb-1">Valor</div>
                                <div class="fatura-valor">R$ {{ number_format($valorAtual, 2, ',', '.') }}</div>
                            </div>
                            <div class="col-md-8">
                                <div class="fatura-info-row">
                                    <span class="label">Mes de referencia</span>
                                    <span class="value">{{ $referenciaAtualLabel }}</span>
                                </div>
                                <div class="fatura-info-row">
                                    <span class="label">Vencimento</span>
                                    <span class="value">{{ $vencimentoAtual?->format('d/m/Y') ?? '-' }}</span>
                                </div>
                                @if ($faturaAtual?->pago_em)
                                    <div class="fatura-info-row">
                                        <span class="label">Pago em</span>
                                        <span class="value">{{ $faturaAtual->pago_em->format('d/m/Y') }}</span>
                                    </div>
                                @endif
                                <div class="fatura-info-row">
                                    <span class="label">Licencas cobraveis</span>
                                    <span class="value">{{ $licencas->reject(fn ($l) => $l->emTrial())->count() }}</span>
                                </div>
                                <div class="fatura-info-row">
                                    <span class="label">Rede</span>
                                    <span class="value">{{ $rede->nome }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Historico de faturas --}}
    <div class="card stretch stretch-full mt-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-1">Historico de faturas</h5>
                <small class="text-muted">
                    Total pago em {{ $anoSelecionado }}:
                    <strong class="text-success">R$ {{ number_format($totalPagoNoAno, 2, ',', '.') }}</strong>
                </small>
            </div>
            @if (count($anosDisponiveis) > 0)
                <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                    <label class="form-label fw-semibold mb-0 text-nowrap fs-13">Ano:</label>
                    <select name="ano" class="form-select form-select-sm" style="max-width: 120px;" onchange="this.form.submit()">
                        @foreach ($anosDisponiveis as $a)
                            <option value="{{ $a }}" @selected($a === $anoSelecionado)>{{ $a }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor</th>
                            <th>Pago em</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faturas as $f)
                            @php
                                $b = $mapaBadge[$f->status] ?? ['cor' => 'secondary', 'rotulo' => $f->status];
                            @endphp
                            <tr>
                                <td>{{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $f->referencia)->locale('pt_BR')->isoFormat('MMMM/YYYY')) }}</td>
                                <td>{{ $f->vencimento->format('d/m/Y') }}</td>
                                <td class="text-end">R$ {{ number_format($f->valor, 2, ',', '.') }}</td>
                                <td>{{ $f->pago_em ? $f->pago_em->format('d/m/Y') : '-' }}</td>
                                <td><span class="badge bg-{{ $b['cor'] }}">{{ $b['rotulo'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma fatura registrada em {{ $anoSelecionado }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: comparar planos --}}
    <div class="modal fade" id="modalComparaPlanos" tabindex="-1" aria-labelledby="modalComparaPlanosLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComparaPlanosLabel">
                        <i class="feather-grid me-2"></i>Compare os planos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">
                        O plano vale por unidade. A troca abaixo altera a licença de
                        <strong>{{ $empresaVigente?->nome ?? 'sua unidade' }}</strong>.
                    </p>
                    <div class="row g-3">
                        @foreach ($todosPlanos as $p)
                            @php $eAtual = $p->id === $plano?->id; @endphp
                            <div class="col-md-6">
                                <div class="plano-compara-card {{ $eAtual ? 'atual' : '' }}">
                                    @if ($eAtual)
                                        <span class="badge bg-primary mb-2 align-self-start">Plano atual</span>
                                    @else
                                        <span class="badge bg-light text-muted mb-2 align-self-start">&nbsp;</span>
                                    @endif
                                    <div class="plano-compara-nome">{{ $p->nome }}</div>
                                    <div class="plano-compara-preco">
                                        @if ($p->preco_por_licenca > 0)
                                            R$ {{ number_format($p->preco_por_licenca, 2, ',', '.') }}<small>/mes por unidade</small>
                                        @else
                                            Gratuito
                                        @endif
                                    </div>
                                    <div class="plano-compara-desc">{{ $p->descricao }}</div>
                                    <ul class="recursos">
                                        <li>
                                            <i class="feather-users text-primary"></i>
                                            <span>{{ $p->max_usuarios }} usuario(s) na unidade</span>
                                        </li>
                                        <li>
                                            <i class="feather-check-circle text-success"></i>
                                            <span>Clientes, Servicos, Produtos, Agenda, Vendas</span>
                                        </li>
                                        <li class="{{ $p->tem_estoque ? '' : 'inativo' }}">
                                            <i class="feather-{{ $p->tem_estoque ? 'check-circle text-success' : 'x-circle text-muted' }}"></i>
                                            <span>Controle de estoque</span>
                                        </li>
                                        <li class="{{ $p->tem_financeiro ? '' : 'inativo' }}">
                                            <i class="feather-{{ $p->tem_financeiro ? 'check-circle text-success' : 'x-circle text-muted' }}"></i>
                                            <span>Financeiro (caixa, contas, despesas)</span>
                                        </li>
                                    </ul>
                                    <div class="plano-compara-rodape">
                                        @if ($eAtual)
                                            <button type="button" class="btn btn-primary w-100" disabled>Plano atual</button>
                                        @elseif ($podeTrocar && $p->slug === \App\Modules\Tenant\Models\Plano::PRO)
                                            <button type="button" class="btn btn-outline-primary w-100 btn-trocar-plano"
                                                data-plano-id="{{ $p->id }}"
                                                data-plano-nome="{{ $p->nome }}"
                                                data-plano-preco="R$ {{ number_format($p->preco_por_licenca, 2, ',', '.') }}/mes">
                                                Fazer upgrade desta unidade
                                            </button>
                                        @else
                                            {{-- Downgrade nao e self-service: e cancelamento parcial de contrato. --}}
                                            <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                                {{ $podeTrocar ? 'Fale com o suporte' : 'Somente o Admin pode trocar' }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <small class="text-muted">
                        O upgrade vale a partir de hoje, com ajuste pro-rata na fatura do mes vigente.
                    </small>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Form (oculto) para submeter a troca de plano (apenas Admin) --}}
    @if ($podeTrocar && $empresaVigente)
        <form id="form-transicionar-plano" method="POST" action="{{ route('assinatura.transicionar') }}" class="d-none">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ $empresaVigente->id }}">
            <input type="hidden" name="plano_id" id="input-plano-id">
        </form>
    @endif

    {{-- Form (oculto) da renovacao do teste — a unidade vem do botao clicado (apenas Admin) --}}
    @if ($podeRenovarTeste)
        <form id="form-renovar-teste" method="POST" action="{{ route('assinatura.renovar-teste') }}" class="d-none">
            @csrf
            <input type="hidden" name="empresa_id" id="input-renovar-empresa-id">
        </form>
    @endif
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-trocar-plano').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.planoId;
            const nome = this.dataset.planoNome;
            const preco = this.dataset.planoPreco;
            const modalEl = document.getElementById('modalComparaPlanos');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            Swal.fire({
                icon: 'question',
                title: 'Fazer upgrade desta unidade?',
                html: 'Confirmar a mudanca para o plano <strong>' + nome + '</strong> (' + preco + ')?<br><small class="text-muted">A fatura do mes sera ajustada pro-rata.</small>',
                showCancelButton: true,
                confirmButtonColor: '#3454d1',
                confirmButtonText: 'Sim, fazer upgrade',
                cancelButtonText: 'Cancelar',
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    document.getElementById('input-plano-id').value = id;
                    document.getElementById('form-transicionar-plano').submit();
                }
            });
        });
    });

    // Renovar o teste: mesma unidade do botao clicado (alerta do topo ou linha da tabela).
    document.querySelectorAll('.btn-renovar-teste').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.empresaId;
            const nome = this.dataset.empresaNome;
            Swal.fire({
                icon: 'question',
                title: 'Renovar o teste desta unidade?',
                html: '<strong>' + nome + '</strong> volta ao plano Pro por mais {{ \App\Modules\Tenant\Models\Empresa::DIAS_DE_TRIAL }} dias, sem cobranca.'
                    + '<br><small class="text-muted">Ao fim do periodo ela retorna ao plano Gratis.</small>',
                showCancelButton: true,
                confirmButtonColor: '#3454d1',
                confirmButtonText: 'Sim, renovar o teste',
                cancelButtonText: 'Cancelar',
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    document.getElementById('input-renovar-empresa-id').value = id;
                    document.getElementById('form-renovar-teste').submit();
                }
            });
        });
    });
});
</script>
@endpush
