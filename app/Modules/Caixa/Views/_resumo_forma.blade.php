{{-- Mesmo dia da timeline, agregado por forma de pagamento (ResumoDiaService::porForma).
     So o lado das ENTRADAS: pauta-se em quando o cliente pagou (a baixa), nao na
     liquidacao do banco. O detalhe linha-a-linha esta na aba "Lista". --}}
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
