@php
    $collapseId = "desp-{$despesa->id}";
    $tabResumoId = "desp-resumo-{$despesa->id}";
    $tabBaixasId = "desp-baixas-{$despesa->id}";

    $saldo = $despesa->saldoRestante();
    $statusValue = $despesa->status->value;
    $venc = $despesa->data_vencimento;
    $vencida = $despesa->estaVencida();

    if ($vencida) {
        $cor = 'danger';
        $statusLabel = 'Vencida';
    } else {
        $cor = match ($statusValue) {
            'pendente' => 'warning',
            'paga' => 'success',
            'cancelada' => 'secondary',
            default => 'secondary',
        };
        $statusLabel = match ($statusValue) {
            'pendente' => 'Pendente',
            'paga' => 'Paga',
            'cancelada' => 'Cancelada',
            default => ucfirst($statusValue),
        };
    }

    $podeBaixar = $statusValue === 'pendente' && $saldo > 0;
    $tituloPrincipal = $despesa->fornecedor_nome ?: $despesa->nome;
    $subtitulo = $despesa->fornecedor_nome ? $despesa->nome : ($despesa->categoria->descricao ?? '');

    $diasParaVencer = $venc ? now()->startOfDay()->diffInDays($venc->copy()->startOfDay(), false) : null;
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
                {{ $tituloPrincipal }}
                @if($subtitulo)
                    <span class="text-muted fw-normal">— {{ $subtitulo }}</span>
                @endif
                @if($despesa->parcela_total)
                    <span class="badge bg-light text-dark ms-2">{{ $despesa->parcela_numero }}/{{ $despesa->parcela_total }}</span>
                @endif
            </div>
            <div class="fs-12 fw-normal text-muted text-truncate-1-line d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-semibold">R$ {{ number_format($despesa->valor, 2, ',', '.') }}</span>
                @if($venc)
                    <span>Vence em {{ $venc->format('d/m/Y') }}</span>
                @endif
                @if($saldo > 0 && $statusValue === 'pendente')
                    <span class="text-danger">Restante: R$ {{ number_format($saldo, 2, ',', '.') }}</span>
                @endif
            </div>
        </button>
        <div class="d-flex align-items-center gap-2 pe-3">
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-soft-{{ $cor }} text-{{ $cor }}">{{ $statusLabel }}</span>
                @if($despesa->categoria)
                    <span class="badge bg-soft-primary text-primary">{{ $despesa->categoria->descricao }}</span>
                @endif
            </div>
            <div class="dropdown">
                <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="0,10">
                    <i class="feather-more-vertical"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item" data-open-payments="#{{ $collapseId }}" data-target-tab="#{{ $tabBaixasId }}">
                            <i class="feather-dollar-sign me-2"></i>Ver baixas
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('despesas.recibo', $despesa) }}" target="_blank" class="dropdown-item">
                            <i class="feather-printer me-2"></i>Imprimir comprovante
                        </a>
                    </li>
                    @if($podeBaixar)
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="{{ route('despesas.baixa-form', $despesa) }}" class="dropdown-item text-primary">
                                <i class="feather-plus-circle me-2"></i>Pagar
                            </a>
                        </li>
                    @endif
                    @can('despesa.editar')
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="{{ route('despesas.edit', $despesa) }}" class="dropdown-item">
                                <i class="feather-edit-3 me-2"></i>Editar
                            </a>
                        </li>
                    @endcan
                    @can('despesa.excluir')
                        <li>
                            <form action="{{ route('despesas.destroy', $despesa) }}" method="POST" data-confirm="Excluir esta despesa?">
                                @csrf @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="feather-trash-2 me-2"></i>Excluir
                                </button>
                            </form>
                        </li>
                    @endcan
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
                <div class="tab-pane fade show active p-3" id="{{ $tabResumoId }}" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Emissão</div>
                            <div class="fw-semibold">{{ $despesa->data_emissao?->format('d/m/Y') ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Vencimento</div>
                            <div class="fw-semibold">{{ $venc?->format('d/m/Y') ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Competência</div>
                            <div class="fw-semibold">{{ $despesa->competencia?->format('m/Y') ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Categoria</div>
                            <div class="fw-semibold">{{ $despesa->categoria->descricao ?? '—' }}</div>
                        </div>

                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Fornecedor</div>
                            <div class="fw-semibold">{{ $despesa->fornecedor_nome ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Documento</div>
                            <div class="fw-semibold">{{ $despesa->documento ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Forma</div>
                            <div class="fw-semibold">
                                @if($despesa->forma_pagamento)
                                    {{ ucfirst($despesa->forma_pagamento->value) }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Parcela</div>
                            <div class="fw-semibold">
                                @if($despesa->parcela_total)
                                    {{ $despesa->parcela_numero }}/{{ $despesa->parcela_total }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="fs-12 text-muted">Valor total</div>
                            <div class="fw-semibold">R$ {{ number_format($despesa->valor, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="fs-12 text-muted">Valor pago</div>
                            <div class="fw-semibold">R$ {{ number_format($despesa->valor_pago, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="fs-12 text-muted">Saldo restante</div>
                            <div class="fw-semibold {{ $saldo > 0 ? 'text-danger' : 'text-success' }}">
                                R$ {{ number_format($saldo, 2, ',', '.') }}
                            </div>
                        </div>

                        @if($despesa->observacoes)
                        <div class="col-12">
                            <div class="fs-12 text-muted">Observações</div>
                            <div>{{ $despesa->observacoes }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="tab-pane fade p-0" id="{{ $tabBaixasId }}" role="tabpanel">
                    @if($despesa->baixas->count())
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
                                    @foreach($despesa->baixas->sortByDesc('data') as $baixa)
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
