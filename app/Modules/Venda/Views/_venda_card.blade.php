@php
    $data = \Carbon\Carbon::parse($venda->data);
    $uid = "{$venda->tipo}-{$venda->id}";
    $collapseId = "venda-{$uid}";
    $tabResumoId = "resumo-{$uid}";
    $tabItensId = "itens-{$uid}";
    $tabSessoesId = "sessoes-{$uid}";
    $tabPagamentosId = "pagamentos-{$uid}";

    $tipoLabel = $venda->tipo === 'produto' ? 'Produto' : 'Serviço';
    $tipoBadge = $venda->tipo === 'produto'
        ? 'bg-soft-warning text-warning'
        : 'bg-soft-info text-info';
    $rotaCancelar = match ($venda->tipo) {
        'avulso' => route('vendas.cancelar-avulso', $venda->id),
        'pacote' => route('vendas.cancelar-pacote', $venda->id),
        'produto' => route('vendas.cancelar-produto', $venda->id),
    };
    $podeCancelar = !in_array($venda->status, ['cancelado', 'cancelada', 'finalizado', 'concluido']);
    $pagamento = $venda->model->pagamento ?? null;

    $rotaEditar = match ($venda->tipo) {
        'avulso' => route('vendas.edit-avulso', $venda->id),
        'pacote' => route('vendas.edit-pacote', $venda->id),
        'produto' => route('vendas.edit-produto', $venda->id),
    };
    $statusEditavel = in_array($venda->status, ['ativo', 'ativa', 'agendado', 'confirmado']);
    $semBaixas = !$pagamento || (float) $pagamento->valor_pago === 0.0;
    $podeEditar = $statusEditavel && $semBaixas;
@endphp

<div class="d-flex align-items-center">
    <div class="d-flex align-items-center border-3 border-start border-{{ $venda->cor }} rounded flex-grow-1">
        <button type="button"
                class="btn btn-link text-reset text-decoration-none text-start flex-grow-1 px-3 py-2 shadow-none"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="false"
                aria-controls="{{ $collapseId }}">
            <div class="fw-semibold mb-1 text-truncate-1-line">
                {{ $venda->cliente }} <span class="text-muted fw-normal">— {{ $venda->servico }}</span>
            </div>
            <div class="fs-12 fw-normal text-muted text-truncate-1-line">
                <span class="fw-semibold">R$ {{ number_format($venda->valor, 2, ',', '.') }}</span>
            </div>
        </button>
        <div class="d-flex align-items-center gap-2 pe-3">
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-soft-{{ $venda->cor }} text-{{ $venda->cor }}">{{ $venda->status_label }}</span>
                <span class="badge {{ $tipoBadge }}">{{ $tipoLabel }}</span>
            </div>
            <div class="dropdown">
                <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">
                    <i class="feather-more-vertical"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a href="javascript:void(0);" class="dropdown-item" data-open-payments="#{{ $collapseId }}" data-target-tab="#{{ $tabPagamentosId }}">
                            <i class="feather-dollar-sign me-2"></i>Ver pagamentos
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('vendas.recibo', [$venda->tipo, $venda->id]) }}" target="_blank" class="dropdown-item">
                            <i class="feather-printer me-2"></i>Imprimir recibo
                        </a>
                    </li>
                    @if($podeEditar)
                        @can('agendamento.editar')
                            <li>
                                <a href="{{ $rotaEditar }}" class="dropdown-item">
                                    <i class="feather-edit-3 me-2"></i>Editar venda
                                </a>
                            </li>
                        @endcan
                    @endif
                    @if($podeCancelar)
                        @can('agendamento.cancelar')
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ $rotaCancelar }}" method="POST" data-confirm="Cancelar esta venda?">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="feather-x-circle me-2"></i>Cancelar venda
                                    </button>
                                </form>
                            </li>
                        @endcan
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

                    @if($venda->tipo === 'produto')
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabItensId }}" role="tab" aria-selected="false">
                                <i class="feather-package me-2"></i>Itens
                            </a>
                        </li>
                    @endif

                    @if($venda->tipo === 'pacote')
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabSessoesId }}" role="tab" aria-selected="false">
                                <i class="feather-calendar me-2"></i>Sessões
                            </a>
                        </li>
                    @endif

                    <li class="nav-item flex-fill border-top" role="presentation">
                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabPagamentosId }}" role="tab" aria-selected="false">
                            <i class="feather-dollar-sign me-2"></i>Pagamentos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                {{-- Aba Resumo --}}
                <div class="tab-pane fade show active p-3" id="{{ $tabResumoId }}" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Data da venda</div>
                            <div class="fw-semibold">{{ $data->format('d/m/Y') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="fs-12 text-muted">Valor total</div>
                            <div class="fw-semibold">R$ {{ number_format($venda->valor, 2, ',', '.') }}</div>
                        </div>
                        @if($pagamento)
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Forma pagamento</div>
                                <div class="fw-semibold">{{ $pagamento->forma_pagamento?->value ? ucfirst($pagamento->forma_pagamento->value) : '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Status pagamento</div>
                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($pagamento->status->value) }}</span>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Pago / Total</div>
                                <div class="fw-semibold">
                                    R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}
                                    / R$ {{ number_format($pagamento->valor, 2, ',', '.') }}
                                </div>
                            </div>
                        @endif

                        @if($venda->tipo === 'avulso')
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Atendente</div>
                                <div class="fw-semibold">{{ $venda->model->atendente->nome ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Início</div>
                                <div class="fw-semibold">{{ $venda->model->inicio->format('d/m/Y H:i') }}</div>
                            </div>
                        @elseif($venda->tipo === 'pacote')
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Atendente</div>
                                <div class="fw-semibold">{{ $venda->model->atendente->nome ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Sessões</div>
                                <div class="fw-semibold">
                                    {{ $venda->model->sessoesRealizadas() }} realizadas / {{ $venda->model->qtd_sessoes }}
                                </div>
                            </div>
                        @elseif($venda->tipo === 'produto')
                            <div class="col-md-3">
                                <div class="fs-12 text-muted">Vendedor</div>
                                <div class="fw-semibold">{{ $venda->model->usuario->nome ?? '—' }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Aba Itens (produto) --}}
                @if($venda->tipo === 'produto')
                    <div class="tab-pane fade p-0" id="{{ $tabItensId }}" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center">Qtd</th>
                                        <th class="text-end">Unit.</th>
                                        <th class="text-end">Desc.</th>
                                        <th class="text-end">Acr.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($venda->model->itens as $item)
                                        <tr>
                                            <td>{{ $item->descricao }}</td>
                                            <td class="text-center">{{ $item->quantidade }}</td>
                                            <td class="text-end">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                                            <td class="text-end {{ $item->desconto > 0 ? 'text-danger' : 'text-muted' }}">
                                                {{ $item->desconto > 0 ? '-R$ ' . number_format($item->desconto, 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="text-end {{ $item->acrescimo > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ $item->acrescimo > 0 ? '+R$ ' . number_format($item->acrescimo, 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="text-end fw-semibold">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum item.</td></tr>
                                    @endforelse
                                </tbody>
                                @if($venda->model->itens->count() > 0 && ($venda->model->desconto > 0 || $venda->model->acrescimo > 0))
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end text-muted border-top">Subtotal:</td>
                                            <td class="text-end border-top">R$ {{ number_format($venda->model->subtotal, 2, ',', '.') }}</td>
                                        </tr>
                                        @if($venda->model->desconto > 0)
                                            <tr>
                                                <td colspan="5" class="text-end text-muted">Desconto:</td>
                                                <td class="text-end text-danger">-R$ {{ number_format($venda->model->desconto, 2, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                        @if($venda->model->acrescimo > 0)
                                            <tr>
                                                <td colspan="5" class="text-end text-muted">Acréscimo:</td>
                                                <td class="text-end text-success">+R$ {{ number_format($venda->model->acrescimo, 2, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                        <tr class="fw-semibold">
                                            <td colspan="5" class="text-end">Total:</td>
                                            <td class="text-end">R$ {{ number_format($venda->model->valor_total, 2, ',', '.') }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Aba Sessões (pacote) --}}
                @if($venda->tipo === 'pacote')
                    <div class="tab-pane fade p-0" id="{{ $tabSessoesId }}" role="tabpanel">
                        @if($venda->model->desconto > 0 || $venda->model->acrescimo > 0)
                            @php
                                $subtotalPacote = $venda->model->valor_total + $venda->model->desconto - $venda->model->acrescimo;
                            @endphp
                            <div class="p-3 border-bottom bg-light-subtle">
                                <div class="d-flex flex-wrap gap-4 fs-13">
                                    <div>
                                        <span class="text-muted">Subtotal:</span>
                                        <span class="fw-semibold ms-1">R$ {{ number_format($subtotalPacote, 2, ',', '.') }}</span>
                                    </div>
                                    @if($venda->model->desconto > 0)
                                        <div>
                                            <span class="text-muted">Desconto:</span>
                                            <span class="fw-semibold text-danger ms-1">-R$ {{ number_format($venda->model->desconto, 2, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if($venda->model->acrescimo > 0)
                                        <div>
                                            <span class="text-muted">Acréscimo:</span>
                                            <span class="fw-semibold text-success ms-1">+R$ {{ number_format($venda->model->acrescimo, 2, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-muted">Total:</span>
                                        <span class="fw-bold ms-1">R$ {{ number_format($venda->model->valor_total, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Horário</th>
                                        <th>Atendente</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($venda->model->agendamentos as $ag)
                                        <tr>
                                            <td>{{ $ag->inicio->format('d/m/Y') }}</td>
                                            <td>{{ $ag->inicio->format('H:i') }} - {{ $ag->fim->format('H:i') }}</td>
                                            <td>{{ $ag->atendente->nome ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-soft-{{ $ag->status->cor() }} text-{{ $ag->status->cor() }}">{{ $ag->status->label() }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma sessão agendada.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Aba Pagamentos --}}
                <div class="tab-pane fade p-0" id="{{ $tabPagamentosId }}" role="tabpanel">
                    @if($pagamento)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Forma</th>
                                        <th>Status</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $pagamento->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $pagamento->forma_pagamento?->value ? ucfirst($pagamento->forma_pagamento->value) : '—' }}</td>
                                        <td><span class="badge bg-soft-secondary text-secondary">{{ ucfirst($pagamento->status->value) }}</span></td>
                                        <td class="text-end">R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
                                        <td class="text-end fw-semibold">R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if($pagamento->baixas->count())
                            <div class="px-3 pt-3 pb-1 fs-12 text-muted fw-semibold">Baixas / Recebimentos</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Forma</th>
                                            <th class="text-end">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pagamento->baixas as $baixa)
                                            <tr>
                                                <td>{{ $baixa->created_at->format('d/m/Y H:i') }}</td>
                                                <td>{{ ucfirst($baixa->forma_pagamento?->value ?? '—') }}</td>
                                                <td class="text-end fw-semibold">R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        <div class="text-center text-muted py-4">Nenhum pagamento registrado.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

