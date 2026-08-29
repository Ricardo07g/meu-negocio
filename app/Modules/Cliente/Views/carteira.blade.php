@extends('layouts.app')

@section('titulo', 'Carteira de clientes - Meu Negocio')
@section('titulo-pagina', 'Carteira de clientes')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clientes.index') }}">Clientes</a></li>
    <li class="breadcrumb-item active">Carteira</li>
@endsection

@section('content')
    @php
        $temBase = $carteira['clientes_com_compra'] > 0;
        $restante = max(0, $iaLimite - $iaConsumo);
        $percentualCota = $iaLimite > 0 ? min(100, (int) round($iaConsumo / $iaLimite * 100)) : 0;
    @endphp

    {{-- Resumo da carteira: sai do SQL, nao depende de IA. --}}
    <div class="row mb-4">
        <div class="col-6 col-lg-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-12">Clientes cadastrados</p>
                <h4 class="mb-0">{{ $carteira['total_clientes'] }}</h4>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-12">Compraram nos últimos {{ $carteira['periodo_meses'] }} meses</p>
                <h4 class="mb-0">{{ $carteira['clientes_com_compra'] }}</h4>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-12">Receita no período</p>
                <h4 class="mb-0">R$ {{ number_format($carteira['receita_total'], 2, ',', '.') }}</h4>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-3">
            <div class="card h-100"><div class="card-body">
                <p class="text-muted mb-1 fs-12">Ticket médio por cliente</p>
                <h4 class="mb-0">R$ {{ number_format($carteira['ticket_medio'], 2, ',', '.') }}</h4>
            </div></div>
        </div>
    </div>

    {{-- Analise por IA: enriquecimento opcional. A pagina vale sem ela. --}}
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0"><i class="feather-zap me-2"></i>Leitura da carteira</h5>

            @if ($iaDisponivel)
                <div class="d-flex align-items-center gap-3">
                    {{-- O consumo fica ao lado do botao de proposito: e nesse instante que o numero importa. --}}
                    <span class="text-muted fs-12" id="ia-consumo-rotulo">
                        {{ number_format($iaConsumo, 0, ',', '.') }} de {{ number_format($iaLimite, 0, ',', '.') }} tokens hoje
                    </span>
                    <button type="button" class="btn btn-primary" id="btn-analisar-ia"
                            @disabled($restante <= 0 || ! $temBase)>
                        <i class="feather-zap me-2"></i>Analisar com IA
                    </button>
                </div>
            @endif
        </div>

        <div class="card-body">
            @if (! $iaDisponivel)
                <div class="alert alert-light border mb-0 d-flex align-items-center" role="alert">
                    <i class="feather-info me-2"></i>
                    <div>
                        A leitura por IA não está disponível nesta unidade.
                        Ela faz parte do plano Pro — a segmentação abaixo continua funcionando normalmente.
                    </div>
                </div>
            @else
                @if ($iaLimite > 0)
                    <div class="progress mb-3" style="height: 6px;" role="progressbar"
                         aria-valuenow="{{ $percentualCota }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $percentualCota >= 100 ? 'bg-warning' : 'bg-primary' }}"
                             id="ia-consumo-barra" style="width: {{ $percentualCota }}%"></div>
                    </div>
                @endif

                @if ($restante <= 0)
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="feather-alert-triangle me-2"></i>
                        <div>Você usou toda a cota de análise de hoje. Ela é renovada amanhã.</div>
                    </div>
                @elseif (! $temBase)
                    <div class="alert alert-light border d-flex align-items-center" role="alert">
                        <i class="feather-info me-2"></i>
                        <div>Ainda não há vendas no período para analisar. São necessários pelo menos {{ $minimoClientes }} clientes com compras.</div>
                    </div>
                @endif

                <div id="ia-erro" class="alert alert-warning d-none" role="alert"></div>

                <div id="ia-resultado" class="{{ $ultimaAnalise ? '' : 'd-none' }}">
                    <p class="text-muted fs-12 mb-2" id="ia-meta">
                        @if ($ultimaAnalise)
                            Gerada em {{ $ultimaAnalise->created_at?->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    <p id="ia-resumo" class="mb-3">{{ $ultimaAnalise->resultado['resumo'] ?? '' }}</p>

                    <div class="row">
                        <div class="col-12 col-lg-4 mb-3">
                            <h6 class="text-success"><i class="feather-thumbs-up me-1"></i>Pontos fortes</h6>
                            <ul class="ps-3 mb-0" id="ia-pontos-fortes">
                                @foreach ($ultimaAnalise->resultado['pontos_fortes'] ?? [] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-12 col-lg-4 mb-3">
                            <h6 class="text-warning"><i class="feather-alert-triangle me-1"></i>Atenção</h6>
                            <ul class="ps-3 mb-0" id="ia-alertas">
                                @foreach ($ultimaAnalise->resultado['alertas'] ?? [] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-12 col-lg-4 mb-3">
                            <h6 class="text-primary"><i class="feather-check-circle me-1"></i>O que fazer</h6>
                            <ul class="ps-3 mb-0" id="ia-acoes">
                                @foreach ($ultimaAnalise->resultado['acoes'] ?? [] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Segmentos --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Segmentos</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Segmento</th>
                            <th class="text-end">Clientes</th>
                            <th class="text-end">% da base</th>
                            <th class="text-end">Receita</th>
                            <th class="text-end">Ticket médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carteira['segmentos'] as $segmento)
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $segmento['cor'] }}">{{ $segmento['label'] }}</span>
                                    <div class="text-muted fs-12 mt-1">{{ $segmento['descricao'] }}</div>
                                </td>
                                <td class="text-end">{{ $segmento['clientes'] }}</td>
                                <td class="text-end">{{ number_format($segmento['percentual'], 1, ',', '.') }}%</td>
                                <td class="text-end">R$ {{ number_format($segmento['receita'], 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($segmento['ticket_medio'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma venda no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($carteira['clientes_sem_compra'] > 0)
                <p class="text-muted fs-12 mt-3 mb-0">
                    <i class="feather-info me-1"></i>
                    {{ $carteira['clientes_sem_compra'] }} cliente(s) cadastrado(s) sem nenhuma compra nos últimos {{ $carteira['periodo_meses'] }} meses.
                </p>
            @endif
        </div>
    </div>

    {{-- Clientes --}}
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Clientes por valor no período</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Segmento</th>
                            <th class="text-center">
                                R / F / V
                                <x-label-info content="Notas de 1 a 5 em cada dimensão.<br><b>R</b> (Recência): há quanto tempo comprou.<br><b>F</b> (Frequência): quantas vezes comprou no período.<br><b>V</b> (Valor): quanto gastou, comparado à média da sua base." />
                            </th>
                            <th class="text-end">Compras</th>
                            <th class="text-end">Valor</th>
                            <th class="text-end">Dias sem comprar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carteira['clientes'] as $linha)
                            <tr>
                                <td>
                                    <a href="{{ route('clientes.show', $linha['cliente_id']) }}">{{ $linha['nome'] }}</a>
                                </td>
                                <td><span class="badge bg-{{ $linha['segmento']->cor() }}">{{ $linha['segmento']->label() }}</span></td>
                                <td class="text-center text-muted fs-12">
                                    {{ $linha['r'] }} / {{ $linha['f'] }} / {{ $linha['m'] }}
                                </td>
                                <td class="text-end">{{ $linha['compras'] }}</td>
                                <td class="text-end">R$ {{ number_format($linha['valor'], 2, ',', '.') }}</td>
                                <td class="text-end">{{ $linha['dias_sem_comprar'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum cliente com compras no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@if ($iaDisponivel)
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var botao = document.getElementById('btn-analisar-ia');
    if (!botao) return;

    var url = '{{ route('clientes.carteira.analisar') }}';
    var token = '{{ csrf_token() }}';
    var rotuloOriginal = botao.innerHTML;

    var caixaErro = document.getElementById('ia-erro');
    var caixaResultado = document.getElementById('ia-resultado');

    function preencherLista(id, itens) {
        var ul = document.getElementById(id);
        ul.innerHTML = '';
        (itens || []).forEach(function (texto) {
            var li = document.createElement('li');
            li.textContent = texto;
            ul.appendChild(li);
        });
    }

    function atualizarConsumo(consumo, limite) {
        var rotulo = document.getElementById('ia-consumo-rotulo');
        if (rotulo) {
            rotulo.textContent = consumo.toLocaleString('pt-BR') + ' de ' + limite.toLocaleString('pt-BR') + ' tokens hoje';
        }
        var barra = document.getElementById('ia-consumo-barra');
        if (barra && limite > 0) {
            barra.style.width = Math.min(100, Math.round(consumo / limite * 100)) + '%';
        }
    }

    botao.addEventListener('click', function () {
        botao.disabled = true;
        botao.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analisando...';
        caixaErro.classList.add('d-none');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json().then(function (d) { return d; }); })
        .then(function (dados) {
            atualizarConsumo(dados.consumo || 0, dados.limite || 0);

            if (!dados.ok) {
                // Quatro motivos distintos, quatro mensagens. "Algo deu errado" nao ajuda ninguem.
                caixaErro.textContent = dados.mensagem || 'Nao foi possivel gerar a analise agora.';
                caixaErro.classList.remove('d-none');
                if (dados.motivo === 'cota') botao.disabled = true;
                return;
            }

            document.getElementById('ia-resumo').textContent = dados.resultado.resumo || '';
            preencherLista('ia-pontos-fortes', dados.resultado.pontos_fortes);
            preencherLista('ia-alertas', dados.resultado.alertas);
            preencherLista('ia-acoes', dados.resultado.acoes);

            document.getElementById('ia-meta').textContent = dados.reaproveitada
                ? 'Reaproveitada da análise de ' + dados.geradaEm + ' — nada mudou na carteira desde então.'
                : 'Gerada em ' + dados.geradaEm;

            caixaResultado.classList.remove('d-none');
        })
        .catch(function () {
            caixaErro.textContent = 'Falha de conexão ao gerar a análise.';
            caixaErro.classList.remove('d-none');
        })
        .finally(function () {
            botao.innerHTML = rotuloOriginal;
            if (!botao.disabled) botao.disabled = false;
        });
    });
});
</script>
@endpush
@endif
