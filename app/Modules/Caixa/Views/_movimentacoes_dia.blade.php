{{-- Timeline do dia da loja: TUDO que movimentou dinheiro, em qualquer forma.
     Fonte: MovimentacaoDiaService (eixo das baixas + sangria/reforco). NAO e o razao
     da conta-caixa — esse vive em /contas/{caixa}/extrato. Ver ADR-0014. --}}
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Descrição</th>
                <th>Forma</th>
                <th>Conta</th>
                <th class="text-end">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimentacoes['linhas'] as $linha)
                @php
                    $entrada = $linha['tipo']->ehEntrada();
                    $cor = $linha['tipo']->cor();
                @endphp
                <tr>
                    <td class="text-muted">{{ $linha['momento']->format('H:i') }}</td>
                    <td>
                        <span class="badge bg-soft-{{ $cor }} text-{{ $cor }}">
                            <i class="{{ $linha['tipo']->icone() }} me-1"></i>{{ $linha['tipo']->label() }}
                        </span>
                    </td>
                    <td>
                        @if($linha['url'])
                            <a href="{{ $linha['url'] }}" class="text-reset fw-semibold">{{ $linha['titulo'] }}</a>
                        @else
                            <span class="fw-semibold">{{ $linha['titulo'] }}</span>
                        @endif
                        @if($linha['estornada'])
                            <span class="badge bg-soft-secondary text-secondary ms-1">Estornado</span>
                        @endif
                        @if($linha['detalhe'])
                            <div class="fs-11 text-muted">{{ $linha['detalhe'] }}</div>
                        @endif
                    </td>
                    <td class="fs-12">{{ $linha['forma'] ?? '—' }}</td>
                    <td class="fs-12 text-nowrap">
                        {{ $linha['conta'] ?? '—' }}
                        @if($linha['tocaGaveta'])
                            {{-- A marca que reconcilia com o bloco de fechamento: so estas linhas
                                 mexem no saldo da gaveta. --}}
                            <i class="feather-archive text-primary ms-1" title="Mexe no saldo da gaveta"></i>
                        @endif
                    </td>
                    <td class="text-end text-nowrap text-{{ $entrada ? 'success' : 'danger' }} {{ $linha['estornada'] ? 'text-decoration-line-through' : 'fw-semibold' }}">
                        {{ $entrada ? '+' : '−' }} R$ {{ number_format($linha['valor'], 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma movimentação neste dia.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(count($movimentacoes['linhas']) > 0)
<p class="text-muted fs-11 mb-0 px-3 py-2 border-top">
    <i class="feather-archive text-primary me-1"></i>Linhas com este ícone são em dinheiro e mexem no
    saldo da gaveta. Cartão, pix e boleto vão direto para a conta da maquineta/banco.
    <span class="mx-1">·</span>Sangria e reforço não entram no resultado do dia: é dinheiro trocando de lugar.
</p>
@endif
