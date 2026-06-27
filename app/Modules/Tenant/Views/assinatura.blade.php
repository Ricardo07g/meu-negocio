@extends('layouts.app')

@section('titulo', 'Minha Assinatura - Meu Negócio')
@section('titulo-pagina', 'Minha Assinatura')
@section('breadcrumb')
    <li class="breadcrumb-item active">Minha Assinatura</li>
@endsection

@use('App\Enums\StatusFatura')

@php
    $statusFatura = $faturaAtual?->status ?? StatusFatura::EmAberto;

    $modulosDisponiveis = [
        ['icone' => 'feather-users', 'nome' => 'Clientes', 'inclui' => true],
        ['icone' => 'feather-briefcase', 'nome' => 'Servicos', 'inclui' => true],
        ['icone' => 'feather-package', 'nome' => 'Produtos', 'inclui' => true],
        ['icone' => 'feather-calendar', 'nome' => 'Agenda', 'inclui' => true],
        ['icone' => 'feather-shopping-bag', 'nome' => 'Vendas', 'inclui' => true],
        ['icone' => 'feather-archive', 'nome' => 'Estoque', 'inclui' => $plano->tem_estoque],
        ['icone' => 'feather-dollar-sign', 'nome' => 'Financeiro (Pagamentos / Despesas / Caixa)', 'inclui' => $plano->tem_financeiro],
    ];

    $limiteEmpresas = (int) $plano->max_empresas;
    $limiteUsuarios = (int) $plano->max_usuarios;
    $percEmpresas = $limiteEmpresas > 0 ? min(100, round($usoEmpresas / $limiteEmpresas * 100)) : 0;
    $percUsuarios = $limiteUsuarios > 0 ? min(100, round($usoUsuarios / $limiteUsuarios * 100)) : 0;
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
        text-transform: capitalize;
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
        text-transform: capitalize;
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
    {{-- Banner: downgrade agendado para o proximo ciclo --}}
    @if ($rede->plano_agendado_id && $rede->planoAgendado)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <div>
                <i class="feather-clock me-1"></i>
                Mudanca de plano agendada: <strong class="text-capitalize">{{ $rede->planoAgendado->nome }}</strong>
                passa a valer em <strong>{{ now()->addMonthNoOverflow()->startOfMonth()->format('d/m/Y') }}</strong>.
                Voce mantem o plano atual ate la.
            </div>
            @if ($podeTrocar)
                <form method="POST" action="{{ route('assinatura.transicionar') }}" class="mb-0">
                    @csrf
                    <input type="hidden" name="plano_id" value="{{ $plano->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Cancelar mudanca agendada</button>
                </form>
            @endif
        </div>
    @endif

    {{-- Top action: comparar planos --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted fs-13">
            Visao geral da assinatura, recursos do plano e historico de faturas.
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalComparaPlanos">
            <i class="feather-grid me-1"></i> Comparar planos
        </button>
    </div>

    {{-- Hero do plano atual --}}
    <div class="card stretch stretch-full assinatura-hero mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge bg-white text-primary mb-2">Plano atual</span>
                    <h2 class="plano-nome mb-2">{{ $plano->nome }}</h2>
                    <p class="plano-descricao mb-0">{{ $plano->descricao ?? 'Plano da rede '.$rede->nome }}</p>
                </div>
                <div class="col-md-5 text-md-end mt-4 mt-md-0">
                    @if ($plano->preco_mensal > 0)
                        <div class="plano-preco">
                            <span class="moeda">R$</span>{{ number_format($plano->preco_mensal, 2, ',', '.') }}<span class="periodo">/mes</span>
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
                    <h5 class="card-title">Recursos inclusos</h5>
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

        {{-- Limites e uso --}}
        <div class="col-lg-6">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card stretch stretch-full uso-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="text-muted fs-12 fw-semibold text-uppercase mb-1">Empresas</div>
                                    <div class="uso-numero">{{ $usoEmpresas }}</div>
                                    <div class="uso-limite">
                                        de {{ $limiteEmpresas > 0 ? $limiteEmpresas : '∞ (ilimitado)' }}
                                    </div>
                                </div>
                                <span class="avatar avatar-md bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="feather-home fs-18"></i>
                                </span>
                            </div>
                            @if ($limiteEmpresas > 0)
                                <div class="progress mt-3">
                                    <div class="progress-bar bg-primary" style="width: {{ $percEmpresas }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card stretch stretch-full uso-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="text-muted fs-12 fw-semibold text-uppercase mb-1">Usuarios</div>
                                    <div class="uso-numero">{{ $usoUsuarios }}</div>
                                    <div class="uso-limite">
                                        de {{ $limiteUsuarios > 0 ? $limiteUsuarios : '∞ (ilimitado)' }}
                                    </div>
                                </div>
                                <span class="avatar avatar-md bg-soft-success text-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="feather-users fs-18"></i>
                                </span>
                            </div>
                            @if ($limiteUsuarios > 0)
                                <div class="progress mt-3">
                                    <div class="progress-bar bg-success" style="width: {{ $percUsuarios }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fatura atual --}}
        <div class="col-12" id="fatura-atual">
            <div class="card stretch stretch-full fatura-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Fatura atual</h5>
                    <span class="badge bg-soft-{{ $statusFatura->cor() }} text-{{ $statusFatura->cor() }} border border-{{ $statusFatura->cor() }}-subtle">
                        {{ $statusFatura->label() }}
                    </span>
                </div>
                <div class="card-body">
                    @php
                        $valorAtual = (float) ($faturaAtual?->valor ?? $plano->preco_mensal);
                        $referenciaAtualLabel = $faturaAtual
                            ? ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $faturaAtual->referencia)->locale('pt_BR')->isoFormat('MMMM/YYYY'))
                            : ucfirst(now()->locale('pt_BR')->isoFormat('MMMM/YYYY'));
                        $vencimentoAtual = $faturaAtual?->vencimento;
                    @endphp
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <div class="text-muted fs-12 fw-semibold text-uppercase mb-1">Valor</div>
                            @if ($valorAtual > 0)
                                <div class="fatura-valor">
                                    R$ {{ number_format($valorAtual, 2, ',', '.') }}
                                </div>
                            @else
                                <div class="fatura-valor">Gratuito</div>
                            @endif
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
                                <span class="label">Plano</span>
                                <span class="value text-capitalize">{{ $plano->nome }}</span>
                            </div>
                            <div class="fatura-info-row">
                                <span class="label">Rede</span>
                                <span class="value">{{ $rede->nome }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @if ($podeTrocar && $faturaAtual && in_array($statusFatura, [StatusFatura::EmAberto, StatusFatura::Vencida], true))
                    <div class="card-footer text-end">
                        <form method="POST" action="{{ route('assinatura.fatura.pagar', $faturaAtual) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-pagar-fatura">
                                <i class="feather-check me-1"></i> Marcar como paga
                            </button>
                        </form>
                    </div>
                @endif
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
                            <th>Plano</th>
                            <th>Status</th>
                            @if ($podeTrocar)
                                <th class="text-end">Acoes</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faturas as $f)
                            <tr>
                                <td>{{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $f->referencia)->locale('pt_BR')->isoFormat('MMMM/YYYY')) }}</td>
                                <td>{{ $f->vencimento->format('d/m/Y') }}</td>
                                <td class="text-end">R$ {{ number_format($f->valor, 2, ',', '.') }}</td>
                                <td>{{ $f->pago_em ? $f->pago_em->format('d/m/Y') : '-' }}</td>
                                <td class="text-capitalize">{{ $f->plano->nome ?? '-' }}</td>
                                <td><span class="badge bg-{{ $f->status->cor() }}">{{ $f->status->label() }}</span></td>
                                @if ($podeTrocar)
                                    <td class="text-end">
                                        @if (in_array($f->status, [StatusFatura::EmAberto, StatusFatura::Vencida], true))
                                            <form method="POST" action="{{ route('assinatura.fatura.pagar', $f) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-success btn-pagar-fatura">
                                                    <i class="feather-check me-1"></i> Marcar paga
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $podeTrocar ? 7 : 6 }}" class="text-center text-muted py-4">Nenhuma fatura registrada em {{ $anoSelecionado }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: comparar planos --}}
    <div class="modal fade" id="modalComparaPlanos" tabindex="-1" aria-labelledby="modalComparaPlanosLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComparaPlanosLabel">
                        <i class="feather-grid me-2"></i>Compare os planos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">Veja o que cada plano inclui e escolha o que melhor atende ao seu negocio.</p>
                    <div class="row g-3">
                        @foreach ($todosPlanos as $p)
                            @php $eAtual = $p->id === $plano->id; @endphp
                            <div class="col-md-6 col-lg-3">
                                <div class="plano-compara-card {{ $eAtual ? 'atual' : '' }}">
                                    @if ($eAtual)
                                        <span class="badge bg-primary mb-2 align-self-start">Plano atual</span>
                                    @else
                                        <span class="badge bg-light text-muted mb-2 align-self-start">&nbsp;</span>
                                    @endif
                                    <div class="plano-compara-nome">{{ $p->nome }}</div>
                                    <div class="plano-compara-preco">
                                        @if ($p->preco_mensal > 0)
                                            R$ {{ number_format($p->preco_mensal, 2, ',', '.') }}<small>/mes</small>
                                        @else
                                            Gratuito
                                        @endif
                                    </div>
                                    <div class="plano-compara-desc">{{ $p->descricao }}</div>
                                    <ul class="recursos">
                                        <li>
                                            <i class="feather-home text-primary"></i>
                                            <span>{{ $p->max_empresas > 0 ? $p->max_empresas.' empresa(s)' : 'Empresas ilimitadas' }}</span>
                                        </li>
                                        <li>
                                            <i class="feather-users text-primary"></i>
                                            <span>{{ $p->max_usuarios > 0 ? $p->max_usuarios.' usuario(s)' : 'Usuarios ilimitados' }}</span>
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
                                        @elseif ($podeTrocar)
                                            @php $previa = $previas[$p->id] ?? ['tipo' => 'upgrade', 'texto' => '']; @endphp
                                            @if ($previa['tipo'] === 'downgrade_bloqueado')
                                                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                                    Indisponivel — limite
                                                </button>
                                                <small class="text-danger d-block mt-2">{{ $previa['texto'] }}</small>
                                            @else
                                                <button type="button" class="btn btn-outline-primary w-100 btn-trocar-plano"
                                                    data-plano-id="{{ $p->id }}"
                                                    data-plano-nome="{{ $p->nome }}"
                                                    data-plano-preco="{{ $p->preco_mensal > 0 ? 'R$ '.number_format($p->preco_mensal, 2, ',', '.').'/mes' : 'Gratuito' }}"
                                                    data-tipo="{{ $previa['tipo'] }}"
                                                    data-previa="{{ $previa['texto'] }}">
                                                    {{ $previa['tipo'] === 'downgrade' ? 'Agendar downgrade' : 'Fazer upgrade' }}
                                                </button>
                                            @endif
                                        @else
                                            <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                                Somente o Admin pode trocar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    @if ($podeTrocar)
                        <small class="text-muted">Upgrade vale na hora (fatura do mes ajustada pro-rata); downgrade passa a valer no proximo ciclo.</small>
                    @else
                        <small class="text-muted">Somente o Admin da rede pode alterar o plano.</small>
                    @endif
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Form (oculto) para submeter a troca de plano (apenas Admin) --}}
    @if ($podeTrocar)
        <form id="form-transicionar-plano" method="POST" action="{{ route('assinatura.transicionar') }}" class="d-none">
            @csrf
            <input type="hidden" name="plano_id" id="input-plano-id">
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
            const tipo = this.dataset.tipo;
            const previa = this.dataset.previa || '';
            const ehDowngrade = tipo === 'downgrade';
            const modalEl = document.getElementById('modalComparaPlanos');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            Swal.fire({
                icon: 'question',
                title: ehDowngrade ? 'Agendar downgrade?' : 'Fazer upgrade?',
                html: 'Mudar para o plano <strong style="text-transform:capitalize;">' + nome + '</strong> (' + preco + ').'
                    + (previa ? '<br><small class="text-muted">' + previa + '</small>' : ''),
                showCancelButton: true,
                confirmButtonColor: '#3454d1',
                confirmButtonText: ehDowngrade ? 'Sim, agendar' : 'Sim, fazer upgrade',
                cancelButtonText: 'Cancelar',
            }).then(function (resultado) {
                if (resultado.value) {
                    document.getElementById('input-plano-id').value = id;
                    document.getElementById('form-transicionar-plano').submit();
                }
            });
        });
    });

    document.querySelectorAll('.btn-pagar-fatura').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            Swal.fire({
                icon: 'question',
                title: 'Marcar como paga?',
                text: 'Confirma o pagamento desta fatura?',
                showCancelButton: true,
                confirmButtonColor: '#3454d1',
                confirmButtonText: 'Sim, marcar',
                cancelButtonText: 'Cancelar',
            }).then(function (resultado) {
                if (resultado.value) form.submit();
            });
        });
    });
});
</script>
@endpush
