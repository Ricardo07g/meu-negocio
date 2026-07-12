@php
    $uid = "{$venda->tipo}-{$venda->id}";
    $tabResumoId = "resumo-{$uid}";
    $tabItensId = "itens-{$uid}";
    $tabSessoesId = "sessoes-{$uid}";
    $tabPagamentosId = "pagamentos-{$uid}";
    $data = \Carbon\Carbon::parse($venda->data);
    $pagamento = $venda->model->pagamento ?? null;
    $valorPagoAtual = $pagamento ? (float) $pagamento->valorPago() : 0.0;
    $totalRecebidoLiquido = $pagamento ? (float) $pagamento->totalRecebidoLiquido() : 0.0;
@endphp

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

            @if($venda->tipo === 'etapas')
                <li class="nav-item flex-fill border-top" role="presentation">
                    <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#{{ $tabSessoesId }}" role="tab" aria-selected="false">
                        <i class="feather-calendar me-2"></i>Etapas
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
                        <div class="fs-12 text-muted">Condição</div>
                        <div class="fw-semibold">{{ $pagamento->condicao_pagamento->label() }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Status pagamento</div>
                        <x-badge-status :cor="$pagamento->status->cor()" :label="$pagamento->status->label()" />
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Recebido (líquido) / Total</div>
                        <div class="fw-semibold">
                            R$ {{ number_format($totalRecebidoLiquido, 2, ',', '.') }}
                            / R$ {{ number_format($pagamento->valor_total, 2, ',', '.') }}
                        </div>
                        @if(abs($totalRecebidoLiquido - $valorPagoAtual) > 0.009)
                            <div class="fs-11 text-muted">Principal quitado: R$ {{ number_format($valorPagoAtual, 2, ',', '.') }}</div>
                        @endif
                    </div>
                @endif

                @if($venda->tipo === 'unico')
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Atendente</div>
                        <div class="fw-semibold">{{ $venda->model->atendente->nome ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Início</div>
                        <div class="fw-semibold">{{ $venda->model->inicio->format('d/m/Y H:i') }}</div>
                    </div>
                @elseif($venda->tipo === 'etapas')
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Atendente</div>
                        <div class="fw-semibold">{{ $venda->model->atendente->nome ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fs-12 text-muted">Etapas</div>
                        <div class="fw-semibold">
                            {{ $venda->model->etapasRealizadas() }} realizadas / {{ $venda->model->qtd_etapas }}
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
                                    <td>
                                        <div class="hstack gap-2">
                                            <x-thumb :url="$item->produto?->imagem_thumb_url" :nome="$item->descricao" icone="feather-package" :circulo="false" classe="avatar-sm" />
                                            <span>{{ $item->descricao }}</span>
                                        </div>
                                    </td>
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

        {{-- Aba Etapas --}}
        @if($venda->tipo === 'etapas')
            <div class="tab-pane fade p-0" id="{{ $tabSessoesId }}" role="tabpanel">
                @if($venda->model->desconto > 0 || $venda->model->acrescimo > 0)
                    @php
                        $subtotalEtapas = $venda->model->valor_total + $venda->model->desconto - $venda->model->acrescimo;
                    @endphp
                    <div class="p-3 border-bottom bg-light-subtle">
                        <div class="d-flex flex-wrap gap-4 fs-13">
                            <div>
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-semibold ms-1">R$ {{ number_format($subtotalEtapas, 2, ',', '.') }}</span>
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
                                        <x-badge-status :cor="$ag->status->cor()" :label="$ag->status->label()" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma etapa agendada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Aba Pagamentos (parcelas) --}}
        <div class="tab-pane fade p-0" id="{{ $tabPagamentosId }}" role="tabpanel">
            @if($pagamento && $pagamento->parcelas->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Forma</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pagamento->parcelas as $parcela)
                                @php $statusEfetivoP = $parcela->statusEfetivo(); @endphp
                                <tr>
                                    <td>{{ $parcela->numero }}/{{ $parcela->total }}</td>
                                    <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                    <td><x-badge-status :cor="$statusEfetivoP->cor()" :label="$statusEfetivoP->label()" /></td>
                                    <td>{{ $parcela->forma_pagamento?->label() ?? '—' }}</td>
                                    <td class="text-end">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($parcela->valorPagoLiquido(), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center text-muted py-4">Nenhum pagamento registrado.</div>
            @endif
        </div>
    </div>
</div>
