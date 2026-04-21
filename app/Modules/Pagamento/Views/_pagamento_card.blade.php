@php
    $collapseId = "pag-{$pagamento->id}";
    $tabResumoId = "pag-resumo-{$pagamento->id}";
    $tabBaixasId = "pag-baixas-{$pagamento->id}";

    $saldo = $pagamento->saldoRestante();
    $status = $pagamento->status->value;

    $cor = match ($status) {
        'pendente' => 'warning',
        'pago' => 'success',
        'cancelado' => 'danger',
        'estornado' => 'secondary',
        default => 'secondary',
    };
    $statusLabel = match ($status) {
        'pendente' => 'Pendente',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        'estornado' => 'Estornado',
        default => ucfirst($status),
    };

    if ($pagamento->agendamento) {
        $origemTipo = 'Avulso';
        $origemBadge = 'bg-light text-dark border';
        $origemDetalhe = $pagamento->agendamento->servico->nome ?? '—';
    } elseif ($pagamento->vendaPacote) {
        $origemTipo = 'Pacote';
        $origemBadge = 'bg-primary';
        $origemDetalhe = $pagamento->vendaPacote->servico->nome ?? '—';
    } elseif ($pagamento->vendaProduto) {
        $origemTipo = 'Produto';
        $origemBadge = 'bg-warning';
        $origemDetalhe = $pagamento->vendaProduto->itens->pluck('descricao')->implode(', ') ?: '—';
    } else {
        $origemTipo = 'Outro';
        $origemBadge = 'bg-secondary';
        $origemDetalhe = '—';
    }

    $podeBaixar = $status === 'pendente' && $saldo > 0;
@endphp

<div class="d-flex align-items-center">
    <div class="d-flex align-items-center border-3 border-start border-{{ $cor }} rounded flex-grow-1">
        <button type="button"
                class="btn btn-link text-reset text-decoration-none text-start flex-grow-1 px-3 py-2 shadow-none"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="false"
                aria-controls="{{ $collapseId }}">
            <div class="fw-semibold mb-1 text-truncate-1-line">
                {{ $pagamento->cliente->nome ?? '—' }}
                <span class="text-muted fw-normal">— {{ $origemDetalhe }}</span>
            </div>
            <div class="fs-12 fw-normal text-muted text-truncate-1-line d-flex align-items-center gap-2">
                <span class="fw-semibold">R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</span>
                @if($saldo > 0 && $status === 'pendente')
                    <span class="text-danger">Restante: R$ {{ number_format($saldo, 2, ',', '.') }}</span>
                @endif
            </div>
        </button>
        <div class="d-flex align-items-center gap-2 pe-3">
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-soft-{{ $cor }} text-{{ $cor }}">{{ $statusLabel }}</span>
                <span class="badge {{ $origemBadge }}">{{ $origemTipo }}</span>
            </div>
            <div class="dropdown">
                <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">
                    <i class="feather-more-vertical"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item" data-open-payments="#{{ $collapseId }}" data-target-tab="#{{ $tabBaixasId }}">
                            <i class="feather-dollar-sign me-2"></i>Ver baixas
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pagamentos.recibo', $pagamento) }}" target="_blank" class="dropdown-item">
                            <i class="feather-printer me-2"></i>Imprimir comprovante
                        </a>
                    </li>
                    @if($podeBaixar)
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="{{ route('pagamentos.baixa-form', $pagamento) }}" class="dropdown-item text-primary">
                                <i class="feather-plus-circle me-2"></i>Receber
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="collapse" id="{{ $collapseId }}">
    <div class="mt-3">
        <div class="card border-top-0 mb-0">
            <div class="card-header p-0">
                <ul class="nav nav-tabs flex-wrap w-100 text-center" role="tablist">
                    <li class="nav-item flex-fill border-top" role="presentation">
                        <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#{{ $tabResumoId }}" role="tab" aria-selected="true">
                            <i class="feather-file-text me-2"></i>Resumo
                        </a>
                    </li>
                    <li class="nav-item flex-fill border-top" role="presentation">
                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabBaixasId }}" role="tab" aria-selected="false">
                            <i class="feather-dollar-sign me-2"></i>Baixas
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                {{-- Aba Resumo --}}
                <div class="tab-pane fade show active p-3" id="{{ $tabResumoId }}" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Data</div>
                            <div class="fw-semibold">{{ $pagamento->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Valor total</div>
                            <div class="fw-semibold">R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Valor pago</div>
                            <div class="fw-semibold">R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Saldo restante</div>
                            <div class="fw-semibold {{ $saldo > 0 ? 'text-danger' : 'text-success' }}">
                                R$ {{ number_format($saldo, 2, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Forma</div>
                            <div class="fw-semibold">
                                @if($pagamento->forma_pagamento)
                                    {{ ucfirst($pagamento->forma_pagamento->value) }}
                                @else
                                    <span class="badge bg-soft-warning text-warning">A prazo</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Origem</div>
                            <div class="fw-semibold">
                                <span class="badge {{ $origemBadge }}">{{ $origemTipo }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-12 text-muted">Descrição origem</div>
                            <div class="fw-semibold">{{ $origemDetalhe }}</div>
                        </div>
                    </div>
                </div>

                {{-- Aba Baixas --}}
                <div class="tab-pane fade p-0" id="{{ $tabBaixasId }}" role="tabpanel">
                    @if($pagamento->baixas->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Forma</th>
                                        <th>Observação</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pagamento->baixas->sortByDesc('data') as $baixa)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                                            <td>{{ ucfirst($baixa->forma_pagamento?->value ?? '—') }}</td>
                                            <td>{{ $baixa->observacao ?? '—' }}</td>
                                            <td class="text-end fw-semibold">R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">Nenhuma baixa registrada.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
