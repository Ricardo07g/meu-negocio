@extends('layouts.app')

@section('titulo', 'Extrato — '.$conta->nome.' - Meu Negócio')
@section('titulo-pagina', 'Extrato — '.$conta->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('contas.index') }}">Contas</a></li>
    <li class="breadcrumb-item active">Extrato</li>
@endsection

@section('content')
    @php use App\Enums\{FormatoExportacao, StatusExportacao, TipoLancamento}; use App\Modules\Conta\Models\Exportacao; @endphp

    {{-- Resumo da conta. So a gaveta (Caixa) tem saldo controlado; banco/carteira sao rotulos. --}}
    <div class="row mb-4">
        <div class="col-md-6 col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            @if($conta->ehProtegida())
                                <span class="text-muted d-block mb-1">Saldo da gaveta</span>
                                <h3 class="fw-bold mb-0">R$ {{ number_format($saldo, 2, ',', '.') }}</h3>
                            @else
                                <span class="text-muted d-block mb-1">Conta de origem</span>
                                <h3 class="fw-bold mb-0 text-muted">—</h3>
                                <span class="text-muted fs-12">Rótulo de recebimento — os valores aparecem no caixa do dia por forma.</span>
                            @endif
                        </div>
                        <div class="avatar-text avatar-lg bg-soft-primary text-primary">
                            <i class="{{ $conta->tipo->icone() }}"></i>
                        </div>
                    </div>
                    <span class="badge bg-soft-secondary text-secondary mt-2">{{ $conta->tipo->label() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Exportar periodo (acima do carrossel): para movimento maior que um mes, gera planilha via job (ADR-0012). --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Exportar período</h5>
        </div>
        <div class="card-body">
            <p class="text-muted fs-12 mb-3">
                Para períodos maiores que um mês, gere uma planilha. O arquivo é preparado em segundo
                plano — a página se atualiza sozinha e o botão de baixar aparece quando ficar pronto.
                <br>Cada arquivo fica disponível por <strong>{{ Exportacao::DIAS_RETENCAO }} dia</strong> e é
                removido automaticamente depois — você também pode excluir manualmente.
            </p>
            <form method="POST" action="{{ route('contas.exportar', $conta) }}" class="row g-3 align-items-end">
                @csrf
                <input type="hidden" name="mes" value="{{ $mesSelecionado->format('Y-m') }}">
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="de">De</label>
                    <input type="date" id="de" name="de"
                           class="form-control @error('de') is-invalid @enderror"
                           value="{{ old('de', $mesSelecionado->copy()->startOfMonth()->format('Y-m-d')) }}" required>
                    @error('de') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="ate">Até</label>
                    <input type="date" id="ate" name="ate"
                           class="form-control @error('ate') is-invalid @enderror"
                           value="{{ old('ate', $mesSelecionado->copy()->endOfMonth()->format('Y-m-d')) }}" required>
                    @error('ate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="formato">Formato</label>
                    <select id="formato" name="formato" class="form-control">
                        @foreach(FormatoExportacao::cases() as $formato)
                            <option value="{{ $formato->value }}" @selected(old('formato') === $formato->value)>{{ $formato->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="feather-download me-2"></i>Exportar
                    </button>
                </div>
            </form>
        </div>

        @if($exportacoes->isNotEmpty())
        <div class="card-body border-top pt-3">
            <h6 class="fw-bold mb-3">Exportações recentes</h6>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Formato</th>
                            <th>Status</th>
                            <th>Expira</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="exportacoes-tbody">
                        @foreach($exportacoes as $exportacao)
                        <tr>
                            <td>{{ $exportacao->periodo_inicio->format('d/m/Y') }} – {{ $exportacao->periodo_fim->format('d/m/Y') }}</td>
                            <td>{{ $exportacao->formato->label() }}</td>
                            <td><span class="badge bg-{{ $exportacao->status->cor() }}">{{ $exportacao->status->label() }}</span></td>
                            <td class="text-muted fs-12">{{ $exportacao->expiraEm()->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 justify-content-end">
                                    @if($exportacao->estaPronta())
                                        <a href="{{ route('contas.exportacoes.baixar', ['conta' => $conta, 'exportacao' => $exportacao]) }}" class="btn btn-sm btn-primary">
                                            <i class="feather-download me-1"></i>Baixar
                                        </a>
                                    @elseif($exportacao->status === StatusExportacao::Erro)
                                        <span class="text-danger fs-12 align-self-center">Falhou</span>
                                    @else
                                        <span class="text-muted fs-12 align-self-center">Processando…</span>
                                    @endif
                                    @if($exportacao->podeExcluir())
                                        <form method="POST" action="{{ route('contas.exportacoes.excluir', ['conta' => $conta, 'exportacao' => $exportacao]) }}" data-confirm="Remover esta exportação?" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Excluir">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Navegacao por mes: a tela mostra 1 mes; periodos maiores saem por exportacao. --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('contas.extrato', ['conta' => $conta, 'mes' => $mesAnterior]) }}" class="btn btn-primary btn-sm py-3">
                    <i class="feather-chevron-left"></i>
                </a>

                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0 text-capitalize">{{ $mesSelecionado->locale('pt_BR')->isoFormat('MMMM [de] YYYY') }}</h5>
                    @if($ehMesAtual)
                        <span class="badge bg-primary">Este mês</span>
                    @else
                        <a href="{{ route('contas.extrato', $conta) }}" class="btn btn-outline-primary btn-sm">Este mês</a>
                    @endif
                </div>

                <a href="{{ route('contas.extrato', ['conta' => $conta, 'mes' => $mesProximo]) }}" class="btn btn-primary btn-sm py-3">
                    <i class="feather-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Extrato de lancamentos do mes (o razao da conta). Para a conta caixa, sao os movimentos da gaveta. --}}
    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0">Lançamentos</h5>
            <div class="d-flex gap-2 fs-12">
                <span class="badge bg-soft-success text-success">Entradas: R$ {{ number_format($entradas, 2, ',', '.') }}</span>
                <span class="badge bg-soft-danger text-danger">Saídas: R$ {{ number_format($saidas, 2, ',', '.') }}</span>
                <span class="badge bg-soft-secondary text-secondary">Saldo do mês: R$ {{ number_format($saldoMes, 2, ',', '.') }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Forma</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lancamentos as $lancamento)
                        @php $credito = $lancamento->tipo === TipoLancamento::Credito; @endphp
                        <tr>
                            <td>{{ $lancamento->data->format('d/m/Y') }}</td>
                            <td>
                                @switch($lancamento->categoria)
                                    @case('sangria')<span class="badge bg-warning">Sangria</span>@break
                                    @case('reforco')<span class="badge bg-info">Reforço</span>@break
                                    @case('estorno')<span class="badge bg-secondary">Estorno</span>@break
                                    @default
                                        <span class="badge bg-{{ $credito ? 'success' : 'danger' }}">{{ $credito ? 'Entrada' : 'Saída' }}</span>
                                @endswitch
                            </td>
                            <td>{{ $lancamento->descricao }}</td>
                            <td>{{ $lancamento->forma_pagamento_nome ?? '—' }}</td>
                            <td class="text-end text-{{ $credito ? 'success' : 'danger' }}">
                                {{ $credito ? '+' : '−' }} R$ {{ number_format($lancamento->valor, 2, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum lançamento neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Polling AJAX: atualiza os badges e libera o "Baixar" sem recarregar a pagina. --}}
    @if($exportacoes->isNotEmpty())
    @php $temProcessando = $exportacoes->contains(fn ($e) => $e->status === StatusExportacao::Processando); @endphp
    <script>
        (function () {
            const tbody = document.getElementById('exportacoes-tbody');
            if (!tbody) return;
            const statusUrl = @json(route('contas.exportacoes.status', $conta));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function acao(e) {
                if (e.pronta) {
                    return '<a href="' + e.urlDownload + '" class="btn btn-sm btn-primary"><i class="feather-download me-1"></i>Baixar</a>';
                }
                if (e.status === 'erro') {
                    return '<span class="text-danger fs-12 align-self-center">Falhou</span>';
                }
                return '<span class="text-muted fs-12 align-self-center">Processando…</span>';
            }

            function excluir(e) {
                if (!e.podeExcluir) return '';
                return '<form method="POST" action="' + e.urlExcluir + '" data-confirm="Remover esta exportação?" class="d-inline">'
                    + '<input type="hidden" name="_token" value="' + csrf + '">'
                    + '<input type="hidden" name="_method" value="DELETE">'
                    + '<button type="submit" class="btn btn-sm btn-light text-danger" title="Excluir"><i class="feather-trash-2"></i></button>'
                    + '</form>';
            }

            function linha(e) {
                return '<tr>'
                    + '<td>' + e.periodo + '</td>'
                    + '<td>' + e.formato + '</td>'
                    + '<td><span class="badge bg-' + e.cor + '">' + e.statusLabel + '</span></td>'
                    + '<td class="text-muted fs-12">' + e.expiraEm + '</td>'
                    + '<td class="text-end"><div class="d-inline-flex gap-2 justify-content-end">' + acao(e) + excluir(e) + '</div></td>'
                    + '</tr>';
            }

            // Re-liga a confirmacao (SweetAlert) nos forms injetados; o handler do layout so
            // pega os que existem no load. Fallback para confirm() nativo se o Swal faltar.
            function bindConfirm(scope) {
                scope.querySelectorAll('form[data-confirm]').forEach(function (form) {
                    if (form.dataset.bound === 'true') return;
                    form.dataset.bound = 'true';
                    form.addEventListener('submit', function (ev) {
                        if (form.dataset.confirmed === 'true') { form.removeAttribute('data-confirmed'); return; }
                        ev.preventDefault();
                        if (typeof Swal === 'undefined') {
                            if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = 'true'; form.submit(); }
                            return;
                        }
                        Swal.fire({
                            icon: 'warning', title: 'Confirmar', text: form.dataset.confirm,
                            showCancelButton: true, confirmButtonColor: '#d33',
                            cancelButtonText: 'Cancelar', confirmButtonText: 'Sim, confirmar'
                        }).then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = 'true'; form.submit(); } });
                    });
                });
            }

            async function checar() {
                try {
                    const resp = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!resp.ok) { setTimeout(checar, 8000); return; }
                    const data = await resp.json();
                    tbody.innerHTML = data.exportacoes.map(linha).join('');
                    bindConfirm(tbody);
                    if (data.processando) { setTimeout(checar, 4000); }
                } catch (_) {
                    setTimeout(checar, 8000);
                }
            }

            @if($temProcessando)
                setTimeout(checar, 4000);
            @endif
        })();
    </script>
    @endif

    <div class="d-flex pt-4">
        <a href="{{ route('contas.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
    </div>
@endsection
