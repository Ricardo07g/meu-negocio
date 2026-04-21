@extends('layouts.app')

@section('titulo', 'Caixa - Meu Negócio')
@section('titulo-pagina', 'Caixa')
@section('breadcrumb')
    <li class="breadcrumb-item active">Caixa</li>
@endsection

@section('content')
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
                        {{ $caixa->observacao }}
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
                            @forelse($caixa->movimentos->sortByDesc('created_at') as $movimento)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($movimento->created_at)->format('H:i') }}</td>
                                <td>
                                    @switch($movimento->tipo->value)
                                        @case('entrada')
                                            <span class="badge bg-success">Entrada</span>
                                            @break
                                        @case('saida')
                                            <span class="badge bg-danger">Saída</span>
                                            @break
                                        @case('sangria')
                                            <span class="badge bg-warning">Sangria</span>
                                            @break
                                        @case('reforco')
                                            <span class="badge bg-info">Reforço</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($movimento->tipo->value) }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $movimento->descricao }}</td>
                                <td>{{ $movimento->forma_pagamento ? ucfirst($movimento->forma_pagamento->value) : '-' }}</td>
                                <td class="text-end">R$ {{ number_format($movimento->valor, 2, ',', '.') }}</td>
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
    @if(!$caixa && !$ehFuturo)
    // Abrir Caixa
    var btnAbrir = document.getElementById('btn-abrir');
    if (btnAbrir) {
        btnAbrir.addEventListener('click', function() {
            Swal.fire({
                title: 'Abrir Caixa',
                html: '<input id="swal-saldo" class="swal2-input" type="number" step="0.01" min="0" placeholder="Saldo de abertura (R$)" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                      '<textarea id="swal-obs" class="swal2-input" rows="3" placeholder="Observação (opcional)" style="width:100%;max-width:100%;height:auto;box-sizing:border-box;"></textarea>',
                showCancelButton: true,
                confirmButtonText: 'Abrir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3454d1',
                preConfirm: function() {
                    var saldo = document.getElementById('swal-saldo').value;
                    if (saldo === '' || saldo === null || parseFloat(saldo) < 0) {
                        Swal.showValidationMessage('Informe o saldo de abertura');
                        return false;
                    }
                    var obs = document.getElementById('swal-obs').value;
                    return { saldo: saldo, obs: obs };
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
    // Sangria
    document.getElementById('btn-sangria').addEventListener('click', function() {
        Swal.fire({
            title: 'Sangria',
            html: '<input id="swal-valor" class="swal2-input" type="number" step="0.01" min="0.01" placeholder="Valor (R$)" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                  '<textarea id="swal-descricao" class="swal2-input" rows="3" placeholder="Descrição" style="width:100%;max-width:100%;height:auto;box-sizing:border-box;"></textarea>',
            showCancelButton: true,
            confirmButtonText: 'Registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3454d1',
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

    // Reforço
    document.getElementById('btn-reforco').addEventListener('click', function() {
        Swal.fire({
            title: 'Reforço',
            html: '<input id="swal-valor" class="swal2-input" type="number" step="0.01" min="0.01" placeholder="Valor (R$)" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                  '<textarea id="swal-descricao" class="swal2-input" rows="3" placeholder="Descrição" style="width:100%;max-width:100%;height:auto;box-sizing:border-box;"></textarea>',
            showCancelButton: true,
            confirmButtonText: 'Registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3454d1',
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

    // Fechar Caixa
    document.getElementById('btn-fechar').addEventListener('click', function() {
        Swal.fire({
            title: 'Fechar Caixa',
            html: '<label class="swal2-input-label">Saldo contado (R$)</label>' +
                  '<input id="swal-saldo" class="swal2-input" type="number" step="0.01" min="0" value="{{ number_format($saldoAtual, 2, '.', '') }}" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                  '<textarea id="swal-obs" class="swal2-input" rows="3" placeholder="Observação (opcional)" style="width:100%;max-width:100%;height:auto;box-sizing:border-box;"></textarea>',
            showCancelButton: true,
            confirmButtonText: 'Fechar Caixa',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3454d1',
            preConfirm: function() {
                var saldo = document.getElementById('swal-saldo').value;
                if (saldo === '' || saldo === null || parseFloat(saldo) < 0) {
                    Swal.showValidationMessage('Informe o saldo contado');
                    return false;
                }
                var obs = document.getElementById('swal-obs').value;
                return { saldo: saldo, obs: obs };
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
});
</script>
@endpush
