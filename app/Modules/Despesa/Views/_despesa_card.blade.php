@php
    $collapseId = "desp-{$despesa->id}";
    $tabResumoId = "desp-resumo-{$despesa->id}";
    $tabParcelasId = "desp-parcelas-{$despesa->id}";

    $valorPago = $despesa->valorPago();
    $totalPago = $despesa->totalPagoLiquido();
    $saldo = $despesa->saldoRestante();
    $status = $despesa->status;
    $cor = $status->cor();
    $statusLabel = $status->label();

    $parcelas = $despesa->parcelas;
    $pagas = $parcelas->where('status', \App\Enums\StatusParcela::Pago)->count();
    $totalAtivas = $parcelas->whereNotIn('status', [\App\Enums\StatusParcela::Cancelado])->count();
    $proxima = $despesa->proximaParcela();

    $condicaoLabel = $despesa->condicao_pagamento->label();
    $tituloPrincipal = $despesa->fornecedor_nome ?: $despesa->nome;
    $subtitulo = $despesa->fornecedor_nome ? $despesa->nome : ($despesa->categoria->descricao ?? '');

    $algumaVencida = $parcelas->contains(fn ($p) => $p->estaVencida());
    if ($algumaVencida && $status === \App\Enums\StatusDespesa::Pendente) {
        $cor = 'danger';
        $statusLabel = 'Vencida';
    }
@endphp

<div class="d-flex align-items-stretch">
    <div class="d-flex align-items-stretch border-3 border-start border-{{ $cor }} rounded flex-grow-1">
        <button type="button"
                class="btn btn-link text-reset text-decoration-none shadow-none p-0 d-flex align-items-center"
                style="flex: 7 1 0;"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="false"
                aria-controls="{{ $collapseId }}">
            <div class="text-start px-3 py-2" style="flex: 4 1 0; min-width: 0;">
                <div class="fs-11 text-muted fw-medium">#{{ $despesa->id }}</div>
                <div class="fw-semibold text-truncate-1-line">
                    {{ $tituloPrincipal }}
                    @if($subtitulo)
                        <span class="text-muted fw-normal">— {{ $subtitulo }}</span>
                    @endif
                    @if($totalAtivas > 1)
                        <span class="badge bg-light text-dark ms-2">{{ $pagas }}/{{ $totalAtivas }} pagas</span>
                    @endif
                </div>
            </div>
            <div class="text-start px-3 py-2 fs-12 text-muted" style="flex: 3 1 0; min-width: 0;">
                <div>
                    Valor: <span class="fw-semibold text-body">R$ {{ number_format($despesa->valor_total, 2, ',', '.') }}</span>
                </div>
                <div>
                    Emissão: {{ $despesa->data_emissao?->format('d/m/Y') ?? '—' }}
                </div>
                @if($saldo > 0 && $algumaVencida)
                    <div class="text-danger mt-1">
                        A pagar: <span class="fw-semibold">R$ {{ number_format($saldo, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </button>
        <div class="d-flex align-items-center justify-content-end gap-2 pe-3 py-2" style="flex: 3 1 0;">
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
                    @if($valorPago > 0)
                    <li>
                        <a href="{{ route('despesas.recibo', $despesa) }}" target="_blank" class="dropdown-item">
                            <i class="feather-printer me-2"></i>Imprimir comprovante
                        </a>
                    </li>
                    @endif
                    @if($proxima)
                        <li>
                            <a href="{{ route('parcelas-despesa.baixa-form', $proxima) }}" class="dropdown-item text-primary">
                                <i class="feather-plus-circle me-2"></i>Pagar próxima parcela
                            </a>
                        </li>
                    @endif
                    @if (! in_array($status, [\App\Enums\StatusDespesa::Paga, \App\Enums\StatusDespesa::Cancelada]))
                        @can('despesa.editar')
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('despesas.cancelar', $despesa) }}" method="POST" data-confirm="Cancelar esta despesa? Todas as parcelas em aberto serão canceladas.">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="dropdown-item text-warning">
                                        <i class="feather-x-circle me-2"></i>Cancelar despesa
                                    </button>
                                </form>
                            </li>
                        @endcan
                    @endif
                    @can('despesa.excluir')
                        @if ($status === \App\Enums\StatusDespesa::Pendente && $valorPago == 0)
                            <li>
                                <form action="{{ route('despesas.destroy', $despesa) }}" method="POST" data-confirm="Excluir esta despesa? Esta acao e irreversivel.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="feather-trash-2 me-2"></i>Excluir
                                    </button>
                                </form>
                            </li>
                        @endif
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
                        <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#{{ $tabResumoId }}" role="tab">
                            <i class="feather-file-text me-2"></i>Resumo
                        </a>
                    </li>
                    <li class="nav-item flex-fill border-top" role="presentation">
                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabParcelasId }}" role="tab">
                            <i class="feather-layers me-2"></i>Parcelas
                            <span class="badge bg-light text-dark ms-1">{{ $parcelas->count() }}</span>
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
                            <div class="fs-12 text-muted">Mês de referência</div>
                            <div class="fw-semibold">{{ $despesa->mes_referencia?->format('m/Y') ?? '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Condição</div>
                            <div class="fw-semibold">{{ $condicaoLabel }}</div>
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
                            <div class="fs-12 text-muted">Parcelas pagas</div>
                            <div class="fw-semibold">{{ $pagas }}/{{ $totalAtivas }}</div>
                        </div>
                        <div class="col-md-3"></div>

                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Valor total</div>
                            <div class="fw-semibold">R$ {{ number_format($despesa->valor_total, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Pago (líquido)</div>
                            <div class="fw-semibold">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                            @if(abs($totalPago - $valorPago) > 0.009)
                                <div class="fs-11 text-muted">Principal quitado: R$ {{ number_format($valorPago, 2, ',', '.') }}</div>
                            @endif
                        </div>
                        <div class="col-md-3">
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

                <div class="tab-pane fade p-0" id="{{ $tabParcelasId }}" role="tabpanel">
                    @if($parcelas->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Forma</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Pago</th>
                                        <th class="text-end">Desconto</th>
                                        <th class="text-end">Multa</th>
                                        <th class="text-end">Juros</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($parcelas as $parcela)
                                        @php
                                            $statusEfetivo = $parcela->statusEfetivo();
                                            $corParcela = $statusEfetivo->cor();
                                            $saldoP = $parcela->saldoRestante();
                                            $podeBaixar = in_array($parcela->status, [\App\Enums\StatusParcela::Pendente, \App\Enums\StatusParcela::Renegociado]) && $saldoP > 0;
                                            $podeRenegociar = in_array($parcela->status, [\App\Enums\StatusParcela::Pendente, \App\Enums\StatusParcela::Renegociado]);
                                            $podeCancelar = in_array($parcela->status, [\App\Enums\StatusParcela::Pendente, \App\Enums\StatusParcela::Renegociado]);
                                            $descontoTotal = (float) $parcela->baixas->sum('desconto');
                                            $multaTotal = (float) $parcela->baixas->sum('multa');
                                            $jurosTotal = (float) $parcela->baixas->sum('juros');
                                        @endphp
                                        <tr>
                                            <td>{{ $parcela->numero }}/{{ $parcela->total }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td><span class="badge bg-soft-{{ $corParcela }} text-{{ $corParcela }}">{{ $statusEfetivo->label() }}</span></td>
                                            <td>{{ $parcela->forma_pagamento?->label() ?? '—' }}</td>
                                            <td class="text-end">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                                            <td class="text-end">R$ {{ number_format($parcela->valorPagoLiquido(), 2, ',', '.') }}</td>
                                            <td class="text-end {{ $descontoTotal > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ $descontoTotal > 0 ? '-R$ ' . number_format($descontoTotal, 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="text-end {{ $multaTotal > 0 ? 'text-warning' : 'text-muted' }}">
                                                {{ $multaTotal > 0 ? 'R$ ' . number_format($multaTotal, 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="text-end {{ $jurosTotal > 0 ? 'text-warning' : 'text-muted' }}">
                                                {{ $jurosTotal > 0 ? 'R$ ' . number_format($jurosTotal, 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="text-center">
                                                @if($podeBaixar || $podeRenegociar || $podeCancelar)
                                                    <div class="dropdown d-inline-block">
                                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                                            <i class="feather-more-horizontal"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            @if($podeBaixar)
                                                            <li>
                                                                <a href="{{ route('parcelas-despesa.baixa-form', $parcela) }}" class="dropdown-item text-primary">
                                                                    <i class="feather-plus-circle me-2"></i>Pagar
                                                                </a>
                                                            </li>
                                                            @endif
                                                            @if($podeCancelar)
                                                            <li>
                                                                <form action="{{ route('parcelas-despesa.cancelar', $parcela) }}" method="POST" data-confirm="Cancelar esta parcela?">
                                                                    @csrf @method('PATCH')
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="feather-x-circle me-2"></i>Cancelar
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            @endif
                                                        </ul>
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if($parcela->observacao)
                                            <tr class="bg-light-subtle">
                                                <td colspan="10" class="fs-12 text-muted">{{ $parcela->observacao }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">Nenhuma parcela.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
