@extends('layouts.app')

@section('titulo', 'Caixa - Meu Negócio')
@section('titulo-pagina', 'Caixa')
@section('breadcrumb')
    <li class="breadcrumb-item active">Caixa</li>
@endsection

@push('css')
<style>
    /* Modais do Caixa — SweetAlert2 customizado */
    .swal-caixa .swal2-html-container { text-align: left; padding: 0 1.5rem !important; margin: 1rem 0 0 !important; }
    .swal-caixa .swal-field { margin-bottom: 1rem; }
    .swal-caixa .swal-field label { display: block; font-size: 0.8rem; font-weight: 600; color: #495057; margin-bottom: 0.35rem; letter-spacing: 0.02em; }
    .swal-caixa .swal-field input.form-control,
    .swal-caixa .swal-field textarea.form-control { border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.95rem; width: 100%; transition: border-color .15s, box-shadow .15s; box-sizing: border-box; }
    .swal-caixa .swal-field input.form-control:focus,
    .swal-caixa .swal-field textarea.form-control:focus { border-color: #3454d1; box-shadow: 0 0 0 0.2rem rgba(52,84,209,.15); outline: 0; }
    .swal-caixa .swal-field .swal-prefix-input { position: relative; width: 100%; }
    .swal-caixa .swal-field .swal-prefix-input .swal-prefix { position: absolute; top: 50%; left: 0.85rem; transform: translateY(-50%); font-weight: 600; color: #6c757d; font-size: 0.95rem; pointer-events: none; z-index: 2; }
    .swal-caixa .swal-field .swal-prefix-input input.form-control { padding-left: 2.5rem; }
    .swal-caixa .swal-resumo { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.5rem; padding: 0.85rem 1rem; margin-bottom: 1rem; }
    .swal-caixa .swal-resumo .row-line { display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0; font-size: 0.9rem; }
    .swal-caixa .swal-resumo .row-line.total { border-top: 1px dashed #ced4da; margin-top: 0.4rem; padding-top: 0.55rem; font-weight: 700; font-size: 1rem; }
    .swal-caixa .swal-resumo .label { color: #6c757d; }
    .swal-caixa .swal-resumo .valor { font-weight: 600; color: #212529; font-variant-numeric: tabular-nums; }
    .swal-caixa .swal-resumo .valor.entrada { color: #198754; }
    .swal-caixa .swal-resumo .valor.saida { color: #dc3545; }
    .swal-caixa #swal-diferenca.diff-positivo { color: #198754; }
    .swal-caixa #swal-diferenca.diff-negativo { color: #dc3545; }
    .swal-caixa #swal-diferenca.diff-zero { color: #6c757d; }
    .swal-caixa .swal-hint { font-size: 0.8rem; color: #6c757d; margin-top: 0.35rem; }
</style>
@endpush

@section('content')
    @php
        // ME-010 v3: Caixa Diario opera por uma unica empresa. Aceita "1 unica"
        // vinda do contexto da listagem (URL `?empresa_id=X`) OU 1 unica
        // empresa acessivel no universo do usuario. Se nenhuma das duas:
        // empty state com picker embutido para escolher.
        $empresasAtuais = (array) session('empresas_atuais', []);
        $contexto = session('empresa_contexto_atual');
        $temEmpresaUnica = is_int($contexto) || count($empresasAtuais) === 1;
    @endphp

    @if (! $temEmpresaUnica)
        @php
            $empresasOpcoes = \App\Modules\Tenant\Models\Empresa::query()
                ->whereIn('id', $empresasAtuais)
                ->orderBy('nome')
                ->get(['id', 'nome']);
        @endphp
        <div class="card stretch stretch-full empty-state-empresa">
            <div class="card-body text-center py-5">
                <div class="empty-state-icon d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="feather-briefcase"></i>
                </div>
                <h5 class="mb-2 fw-semibold">Selecione uma empresa para operar o caixa</h5>
                <p class="text-muted mb-4 mx-auto" style="max-width: 460px;">
                    O caixa e isolado por empresa. Voce tem acesso a {{ count($empresasAtuais) }} empresas — escolha qual delas voce quer operar agora.
                </p>
                <div class="d-flex justify-content-center">
                    <div class="empty-state-picker">
                        <select class="form-select" data-empresa-inline aria-label="Selecionar empresa">
                            <option value="" disabled selected>Escolha uma empresa…</option>
                            @foreach ($empresasOpcoes as $opcao)
                                <option value="{{ $opcao->id }}">{{ $opcao->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        @push('css')
        <style>
            .empty-state-empresa .empty-state-icon {
                width: 72px;
                height: 72px;
                border-radius: 50%;
                background: rgba(52, 84, 209, 0.1);
            }
            .empty-state-empresa .empty-state-icon i {
                font-size: 32px;
                color: #3454d1;
            }
            .empty-state-empresa .empty-state-picker {
                width: 100%;
                max-width: 320px;
            }
        </style>
        @endpush

        @push('js')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const empresaInline = document.querySelector('[data-empresa-inline]');
            if (! empresaInline) return;
            empresaInline.addEventListener('change', function () {
                if (! this.value) return;
                const url = new URL(window.location.href);
                url.searchParams.set('empresa_id', this.value);
                window.location.assign(url.toString());
            });
        });
        </script>
        @endpush

        @php return; @endphp
    @endif

    @include('partials.filtro-empresa-listagem', ['permiteTodas' => false])

    {{-- Navegacao por data --}}
    @php
        $dataAnterior = $dataSelecionada->copy()->subDay()->toDateString();
        $dataProxima = $dataSelecionada->copy()->addDay()->toDateString();
        $ehHoje = $dataSelecionada->isToday();
        $ehFuturo = $dataSelecionada->isFuture();
    @endphp

    {{-- Botao Abrir Caixa (quando nao existe caixa nesse dia) --}}
    @if(!$caixa && !$ehFuturo)
    @can('financeiro.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <button type="button" class="btn btn-primary w-100" id="btn-abrir">
                <i class="feather-plus me-2"></i>Abrir Caixa
            </button>
        </div>
    </div>

    <form id="form-abrir" action="{{ route('caixas.store') }}" method="POST" style="display:none;">
        @csrf
        <input type="hidden" name="data" value="{{ $dataSelecionada->toDateString() }}">
        <input type="hidden" name="saldo_abertura" id="abrir-saldo">
        <input type="hidden" name="observacao" id="abrir-observacao">
    </form>
    @endcan
    @endif

    <div class="card stretch stretch-full mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('caixas.index', ['data' => $dataAnterior]) }}" class="btn btn-primary btn-sm py-3">
                    <i class="feather-chevron-left"></i>
                </a>

                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0">{{ $dataSelecionada->format('d/m/Y') }}</h5>
                    @if($ehHoje)
                        <span class="badge bg-primary">Hoje</span>
                    @endif
                    @if(!$ehHoje)
                        <a href="{{ route('caixas.index') }}" class="btn btn-outline-primary btn-sm">Hoje</a>
                    @endif
                </div>

                <a href="{{ route('caixas.index', ['data' => $dataProxima]) }}" class="btn btn-primary btn-sm py-3">
                    <i class="feather-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Panorama do dia por forma de pagamento: pauta-se em QUANDO O CLIENTE PAGOU
         (a baixa), nao na liquidacao. Independe de haver caixa aberto e e um eixo
         disjunto do saldo da gaveta (a tabela "Movimentos" abaixo). --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recebimentos do dia por forma</h5>
            <span class="badge bg-soft-primary text-primary">Líquido: R$ {{ number_format($resumo['liquido'], 2, ',', '.') }}</span>
        </div>
        <div class="card-body">
            <p class="text-muted fs-12 mb-3">
                <i class="feather-info me-1"></i>Tudo que o cliente pagou neste dia, por forma (pela data do pagamento — não pela liquidação do banco). A tabela "Movimentos" abaixo é só a gaveta de dinheiro.
            </p>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Forma</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Recebido</th>
                            <th class="text-end">Estornado</th>
                            <th class="text-end">Líquido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resumo['linhas'] as $linha)
                        <tr>
                            <td>{{ $linha['forma'] }}</td>
                            <td class="text-center text-muted">{{ $linha['qtd'] }}</td>
                            <td class="text-end text-success">R$ {{ number_format($linha['recebido'], 2, ',', '.') }}</td>
                            <td class="text-end {{ $linha['estornado'] > 0 ? 'text-danger' : 'text-muted' }}">
                                @if($linha['estornado'] > 0)− R$ {{ number_format($linha['estornado'], 2, ',', '.') }}@else—@endif
                            </td>
                            <td class="text-end fw-semibold">R$ {{ number_format($linha['liquido'], 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum recebimento registrado neste dia.</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($resumo['linhas']) > 0)
                    <tfoot>
                        <tr class="fw-bold border-top">
                            <td>Total</td>
                            <td></td>
                            <td class="text-end text-success">R$ {{ number_format($resumo['totalRecebido'], 2, ',', '.') }}</td>
                            <td class="text-end {{ $resumo['totalEstornado'] > 0 ? 'text-danger' : 'text-muted' }}">
                                @if($resumo['totalEstornado'] > 0)− R$ {{ number_format($resumo['totalEstornado'], 2, ',', '.') }}@else—@endif
                            </td>
                            <td class="text-end">R$ {{ number_format($resumo['liquido'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($caixa)
        {{-- Summary cards --}}
        <div class="row mb-4">
            <div class="col-xxl-3 col-md-6 mb-3">
                <div class="card stretch stretch-full">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1">Saldo Abertura</p>
                        <h4 class="mb-0">R$ {{ number_format($caixa->saldo_abertura, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6 mb-3">
                <div class="card stretch stretch-full">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1">Total Entradas</p>
                        <h4 class="mb-0 text-success">R$ {{ number_format($totalEntradas + $totalReforcos, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6 mb-3">
                <div class="card stretch stretch-full">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1">Total Saídas</p>
                        <h4 class="mb-0 text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6 mb-3">
                <div class="card stretch stretch-full">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1">Saldo Atual</p>
                        <h4 class="mb-0 text-primary">R$ {{ number_format($saldoAtual, 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action buttons (caixa aberto) --}}
        @if($caixa->status->value === 'aberto')
        <div class="row mb-4">
            <div class="col-12">
                <div class="hstack gap-2">
                    <button type="button" class="btn btn-warning" id="btn-sangria">
                        <i class="feather-minus-circle me-2"></i>Sangria
                    </button>
                    <button type="button" class="btn btn-info" id="btn-reforco">
                        <i class="feather-plus-circle me-2"></i>Reforço
                    </button>
                    <button type="button" class="btn btn-danger ms-auto" id="btn-fechar">
                        <i class="feather-lock me-2"></i>Fechar Caixa
                    </button>
                </div>
            </div>
        </div>

        {{-- Hidden forms --}}
        <form id="form-sangria" action="{{ route('caixas.sangria', $caixa) }}" method="POST" style="display:none;">
            @csrf
            <input type="hidden" name="valor" id="sangria-valor">
            <input type="hidden" name="descricao" id="sangria-descricao">
        </form>

        <form id="form-reforco" action="{{ route('caixas.reforco', $caixa) }}" method="POST" style="display:none;">
            @csrf
            <input type="hidden" name="valor" id="reforco-valor">
            <input type="hidden" name="descricao" id="reforco-descricao">
        </form>

        <form id="form-fechar" action="{{ route('caixas.fechar', $caixa) }}" method="POST" style="display:none;">
            @csrf
            @method('PATCH')
            <input type="hidden" name="saldo_fechamento" id="fechar-saldo">
            <input type="hidden" name="observacao" id="fechar-observacao">
        </form>
        @endif

        {{-- Info fechamento (caixa fechado) --}}
        @if($caixa->status->value === 'fechado')
        @can('financeiro.editar')
        <div class="row mb-4">
            <div class="col-12">
                <div class="hstack gap-2">
                    <button type="button" class="btn btn-success ms-auto" id="btn-reabrir">
                        <i class="feather-unlock me-2"></i>Reabrir Caixa
                    </button>
                </div>
            </div>
        </div>

        <form id="form-reabrir" action="{{ route('caixas.reabrir', $caixa) }}" method="POST" style="display:none;">
            @csrf
            @method('PATCH')
            <input type="hidden" name="motivo" id="reabrir-motivo">
        </form>
        @endcan

        <div class="card stretch stretch-full mb-4">
            <div class="card-header">
                <h5 class="card-title">Informações do Fechamento</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <strong>Saldo Contado:</strong><br>
                        R$ {{ number_format($caixa->saldo_fechamento, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3 mb-3">
                        @php $diferenca = $caixa->saldo_fechamento - $saldoAtual; @endphp
                        <strong>Diferença:</strong><br>
                        <span class="{{ $diferenca >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($diferenca, 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Fechado por:</strong><br>
                        {{ $caixa->fechadoPor->nome ?? '-' }}
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Fechado em:</strong><br>
                        {{ $caixa->fechado_em ? \Carbon\Carbon::parse($caixa->fechado_em)->format('d/m/Y H:i') : '-' }}
                    </div>
                    @if($caixa->observacao)
                    <div class="col-12">
                        <strong>Observação:</strong><br>
                        <span style="white-space: pre-line;">{{ $caixa->observacao }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Movimentos table --}}
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Movimentos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Horário</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Forma Pagamento</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($caixa->lancamentos->sortByDesc('created_at') as $lancamento)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($lancamento->created_at)->format('H:i') }}</td>
                                <td>
                                    @switch($lancamento->categoria)
                                        @case('movimento')
                                            @if($lancamento->tipo === \App\Enums\TipoLancamento::Credito)
                                                <span class="badge bg-success">Entrada</span>
                                            @else
                                                <span class="badge bg-danger">Saída</span>
                                            @endif
                                            @break
                                        @case('sangria')
                                            <span class="badge bg-warning">Sangria</span>
                                            @break
                                        @case('reforco')
                                            <span class="badge bg-info">Reforço</span>
                                            @break
                                        @case('estorno')
                                            <span class="badge bg-secondary">Estorno</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($lancamento->categoria) }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $lancamento->descricao }}</td>
                                <td>{{ $lancamento->forma_pagamento_nome ?? '-' }}</td>
                                <td class="text-end">R$ {{ number_format($lancamento->valor, 2, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum movimento registrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @else
        {{-- Sem caixa neste dia --}}
        <div class="card stretch stretch-full">
            <div class="card-body text-center py-5">
                <i class="feather-inbox text-muted" style="font-size: 48px;"></i>
                <p class="text-muted mt-3 mb-0">Nenhum caixa registrado neste dia.</p>
            </div>
        </div>
    @endif
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formatBRL = function(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    @if(!$caixa && !$ehFuturo)
    // ABRIR
    var btnAbrir = document.getElementById('btn-abrir');
    if (btnAbrir) {
        btnAbrir.addEventListener('click', function() {
            Swal.fire({
                title: 'Abrir Caixa',
                iconHtml: '<i class="feather-unlock" style="font-size:28px;color:#3454d1;"></i>',
                customClass: { popup: 'swal-caixa' },
                width: 480,
                html: `
                    <div class="swal-field">
                        <label>Saldo de abertura</label>
                        <div class="swal-prefix-input">
                            <span class="swal-prefix">R$</span>
                            <input id="swal-saldo" class="form-control" type="number" step="0.01" min="0" placeholder="0,00" autofocus>
                        </div>
                        <div class="swal-hint">Valor em dinheiro disponível no caixa ao abrir.</div>
                    </div>
                    <div class="swal-field">
                        <label>Observação (opcional)</label>
                        <textarea id="swal-obs" class="form-control" rows="5" placeholder="Ex: Troco inicial deixado pela gerência..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="feather-check me-1"></i> Abrir Caixa',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3454d1',
                focusConfirm: false,
                preConfirm: function() {
                    var saldo = document.getElementById('swal-saldo').value;
                    if (saldo === '' || saldo === null || parseFloat(saldo) < 0) {
                        Swal.showValidationMessage('Informe o saldo de abertura');
                        return false;
                    }
                    return { saldo: saldo, obs: document.getElementById('swal-obs').value };
                }
            }).then(function(result) {
                if (result.value) {
                    document.getElementById('abrir-saldo').value = result.value.saldo;
                    document.getElementById('abrir-observacao').value = result.value.obs;
                    document.getElementById('form-abrir').submit();
                }
            });
        });
    }
    @endif

    @if($caixa && $caixa->status->value === 'aberto')
    // SANGRIA
    document.getElementById('btn-sangria').addEventListener('click', function() {
        Swal.fire({
            title: 'Registrar Sangria',
            iconHtml: '<i class="feather-minus-circle" style="font-size:28px;color:#ffc107;"></i>',
            customClass: { popup: 'swal-caixa' },
            width: 480,
            html: `
                <div class="swal-hint mb-3" style="margin-top:-0.25rem;">Retirada de dinheiro do caixa (ex: depósito bancário, pagamento).</div>
                <div class="swal-field">
                    <label>Valor da sangria</label>
                    <div class="swal-prefix-input">
                        <span class="swal-prefix">R$</span>
                        <input id="swal-valor" class="form-control" type="number" step="0.01" min="0.01" placeholder="0,00" autofocus>
                    </div>
                </div>
                <div class="swal-field">
                    <label>Descrição</label>
                    <textarea id="swal-descricao" class="form-control" rows="5" placeholder="Ex: Depósito bancário..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="feather-check me-1"></i> Registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3454d1',
            focusConfirm: false,
            preConfirm: function() {
                var valor = document.getElementById('swal-valor').value;
                var descricao = document.getElementById('swal-descricao').value;
                if (!valor || parseFloat(valor) <= 0) {
                    Swal.showValidationMessage('Informe um valor válido');
                    return false;
                }
                if (!descricao || descricao.trim() === '') {
                    Swal.showValidationMessage('Informe uma descrição');
                    return false;
                }
                return { valor: valor, descricao: descricao };
            }
        }).then(function(result) {
            if (result.value) {
                document.getElementById('sangria-valor').value = result.value.valor;
                document.getElementById('sangria-descricao').value = result.value.descricao;
                document.getElementById('form-sangria').submit();
            }
        });
    });

    // REFORÇO
    document.getElementById('btn-reforco').addEventListener('click', function() {
        Swal.fire({
            title: 'Registrar Reforço',
            iconHtml: '<i class="feather-plus-circle" style="font-size:28px;color:#0dcaf0;"></i>',
            customClass: { popup: 'swal-caixa' },
            width: 480,
            html: `
                <div class="swal-hint mb-3" style="margin-top:-0.25rem;">Entrada de dinheiro no caixa (ex: aporte, troco trazido).</div>
                <div class="swal-field">
                    <label>Valor do reforço</label>
                    <div class="swal-prefix-input">
                        <span class="swal-prefix">R$</span>
                        <input id="swal-valor" class="form-control" type="number" step="0.01" min="0.01" placeholder="0,00" autofocus>
                    </div>
                </div>
                <div class="swal-field">
                    <label>Descrição</label>
                    <textarea id="swal-descricao" class="form-control" rows="5" placeholder="Ex: Aporte do proprietário..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="feather-check me-1"></i> Registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3454d1',
            focusConfirm: false,
            preConfirm: function() {
                var valor = document.getElementById('swal-valor').value;
                var descricao = document.getElementById('swal-descricao').value;
                if (!valor || parseFloat(valor) <= 0) {
                    Swal.showValidationMessage('Informe um valor válido');
                    return false;
                }
                if (!descricao || descricao.trim() === '') {
                    Swal.showValidationMessage('Informe uma descrição');
                    return false;
                }
                return { valor: valor, descricao: descricao };
            }
        }).then(function(result) {
            if (result.value) {
                document.getElementById('reforco-valor').value = result.value.valor;
                document.getElementById('reforco-descricao').value = result.value.descricao;
                document.getElementById('form-reforco').submit();
            }
        });
    });

    // FECHAR (com resumo e diferença em tempo real)
    const saldoEsperado = {{ number_format($saldoAtual, 2, '.', '') }};
    const totalEntradas = {{ number_format($totalEntradas + $totalReforcos, 2, '.', '') }};
    const totalSaidas = {{ number_format($totalSaidas, 2, '.', '') }};
    const saldoAbertura = {{ number_format($caixa->saldo_abertura ?? 0, 2, '.', '') }};

    document.getElementById('btn-fechar').addEventListener('click', function() {
        Swal.fire({
            title: 'Fechar Caixa',
            iconHtml: '<i class="feather-lock" style="font-size:28px;color:#dc3545;"></i>',
            customClass: { popup: 'swal-caixa' },
            width: 520,
            html: `
                <div class="swal-resumo">
                    <div class="row-line"><span class="label">Saldo de abertura</span><span class="valor">${formatBRL(saldoAbertura)}</span></div>
                    <div class="row-line"><span class="label">Total entradas</span><span class="valor entrada">+ ${formatBRL(totalEntradas)}</span></div>
                    <div class="row-line"><span class="label">Total saídas</span><span class="valor saida">− ${formatBRL(totalSaidas)}</span></div>
                    <div class="row-line total"><span class="label">Saldo esperado</span><span class="valor">${formatBRL(saldoEsperado)}</span></div>
                </div>
                <div class="swal-field">
                    <label>Saldo contado (dinheiro em caixa)</label>
                    <div class="swal-prefix-input">
                        <span class="swal-prefix">R$</span>
                        <input id="swal-saldo" class="form-control" type="number" step="0.01" min="0" value="${saldoEsperado.toFixed(2)}" autofocus>
                    </div>
                    <div class="swal-hint">Diferença: <strong id="swal-diferenca" class="diff-zero">R$ 0,00</strong></div>
                </div>
                <div class="swal-field">
                    <label>Observação (opcional)</label>
                    <textarea id="swal-obs" class="form-control" rows="5" placeholder="Ex: Sobra de troco, ajuste manual..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="feather-lock me-1"></i> Fechar Caixa',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            focusConfirm: false,
            onOpen: function() {
                const inp = document.getElementById('swal-saldo');
                const dif = document.getElementById('swal-diferenca');
                const atualizar = function() {
                    const contado = parseFloat(inp.value || 0);
                    const delta = contado - saldoEsperado;
                    dif.textContent = (delta >= 0 ? '+ ' : '− ') + formatBRL(Math.abs(delta));
                    dif.classList.remove('diff-positivo', 'diff-negativo', 'diff-zero');
                    if (Math.abs(delta) < 0.005) dif.classList.add('diff-zero');
                    else if (delta > 0) dif.classList.add('diff-positivo');
                    else dif.classList.add('diff-negativo');
                };
                inp.addEventListener('input', atualizar);
                atualizar();
                inp.select();
            },
            preConfirm: function() {
                var saldo = document.getElementById('swal-saldo').value;
                if (saldo === '' || saldo === null || parseFloat(saldo) < 0) {
                    Swal.showValidationMessage('Informe o saldo contado');
                    return false;
                }
                return { saldo: saldo, obs: document.getElementById('swal-obs').value };
            }
        }).then(function(result) {
            if (result.value) {
                document.getElementById('fechar-saldo').value = result.value.saldo;
                document.getElementById('fechar-observacao').value = result.value.obs;
                document.getElementById('form-fechar').submit();
            }
        });
    });
    @endif

    @if($caixa && $caixa->status->value === 'fechado')
    @can('financeiro.editar')
    // REABRIR
    var btnReabrir = document.getElementById('btn-reabrir');
    if (btnReabrir) {
        btnReabrir.addEventListener('click', function() {
            Swal.fire({
                title: 'Reabrir Caixa',
                iconHtml: '<i class="feather-unlock" style="font-size:28px;color:#198754;"></i>',
                customClass: { popup: 'swal-caixa' },
                width: 500,
                html: `
                    <div class="swal-hint mb-3" style="margin-top:-0.25rem;">
                        Esta ação reverte o fechamento do caixa e registra um log de auditoria.
                        Informe o motivo para que fique salvo no histórico.
                    </div>
                    <div class="swal-field">
                        <label>Motivo da reabertura <span style="color:#dc3545;">*</span></label>
                        <textarea id="swal-motivo" class="form-control" rows="5" placeholder="Ex: Baixa de pagamento esquecida, ajuste de valor contado..." autofocus></textarea>
                        <div class="swal-hint">Mínimo 5 caracteres. Ficará registrado na observação do caixa.</div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="feather-unlock me-1"></i> Reabrir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                focusConfirm: false,
                preConfirm: function() {
                    var motivo = document.getElementById('swal-motivo').value.trim();
                    if (motivo.length < 5) {
                        Swal.showValidationMessage('Informe um motivo com ao menos 5 caracteres');
                        return false;
                    }
                    return { motivo: motivo };
                }
            }).then(function(result) {
                if (result.value) {
                    document.getElementById('reabrir-motivo').value = result.value.motivo;
                    document.getElementById('form-reabrir').submit();
                }
            });
        });
    }
    @endcan
    @endif
});
</script>
@endpush
