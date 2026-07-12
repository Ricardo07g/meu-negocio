@php
    $uid = "{$venda->tipo}-{$venda->id}";
    $collapseId = "venda-{$uid}";

    $tipoLabel = match ($venda->tipo) {
        'produto' => 'Produto',
        'etapas' => 'Em Etapas',
        default => 'Único',
    };
    $tipoBadge = $venda->tipo === 'produto'
        ? 'bg-soft-warning text-warning'
        : 'bg-soft-info text-info';

    $rotaCancelar = match ($venda->tipo) {
        'unico' => route('vendas.cancelar-unico', $venda->id),
        'etapas' => route('vendas.cancelar-etapas', $venda->id),
        'produto' => route('vendas.cancelar-produto', $venda->id),
    };
    $rotaExcluir = match ($venda->tipo) {
        'unico' => route('vendas.excluir-unico', $venda->id),
        'etapas' => route('vendas.excluir-etapas', $venda->id),
        'produto' => route('vendas.excluir-produto', $venda->id),
    };

    $pagamento = $venda->model->pagamento ?? null;
    $valorPagoAtual = $pagamento ? (float) $pagamento->valorPago() : 0.0;

    $podeCancelar = !in_array($venda->status, ['cancelado', 'cancelada', 'finalizado', 'concluido']);
    $podeExcluir = !in_array($venda->status, ['finalizado', 'concluido']);

    $vendaCancelada = in_array($venda->status, ['cancelado', 'cancelada']);
    $podeImprimirRecibo = !$vendaCancelada && $pagamento && $valorPagoAtual > 0.0;

    $temAcaoDestrutiva = ($podeCancelar && auth()->user()->can('agendamento.cancelar'))
        || ($podeExcluir && auth()->user()->can('agendamento.excluir'));
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
                <x-badge-status :cor="$venda->cor" :label="$venda->status_label" />
                <span class="badge {{ $tipoBadge }}">{{ $tipoLabel }}</span>
            </div>
            <div class="dropdown">
                <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">
                    <i class="feather-more-vertical"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    @can('agendamento.ver')
                        <li>
                            <a href="{{ route('vendas.show', [$venda->tipo, $venda->id]) }}" class="dropdown-item">
                                <i class="feather-eye me-2"></i>Ver detalhes
                            </a>
                        </li>
                    @endcan
                    @if($podeImprimirRecibo)
                        <li>
                            <a href="javascript:void(0)" class="dropdown-item"
                               data-bs-toggle="modal" data-bs-target="#modalRecibo"
                               data-recibo-url="{{ route('vendas.recibo', [$venda->tipo, $venda->id]) }}"
                               data-recibo-titulo="Recibo #{{ str_pad($venda->id, 6, '0', STR_PAD_LEFT) }}">
                                <i class="feather-printer me-2"></i>Imprimir recibo
                            </a>
                        </li>
                    @endif
                    @if($temAcaoDestrutiva)
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    @if($podeCancelar)
                        @can('agendamento.cancelar')
                            <li>
                                <form action="{{ $rotaCancelar }}" method="POST" data-confirm="Cancelar esta venda? Os lançamentos (estoque/pagamento) serão desfeitos, mas a venda continua na lista como cancelada.">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="feather-x-circle me-2"></i>Cancelar venda
                                    </button>
                                </form>
                            </li>
                        @endcan
                    @endif
                    @if($podeExcluir)
                        @can('agendamento.excluir')
                            <li>
                                <form action="{{ $rotaExcluir }}" method="POST" data-confirm="Remover esta venda? Ela sairá da lista e os lançamentos (estoque/pagamento) serão desfeitos.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="feather-trash-2 me-2"></i>Excluir venda
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
        @include('venda::_venda_detalhe', ['venda' => $venda])
    </div>
</div>
