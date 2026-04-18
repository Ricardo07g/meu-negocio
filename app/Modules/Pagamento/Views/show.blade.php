@extends('layouts.app')

@section('titulo', 'Pagamento - Meu Negócio')
@section('titulo-pagina', 'Detalhes do Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pagamentos.index') }}">Pagamentos</a></li>
    <li class="breadcrumb-item active">Detalhes</li>
@endsection

@section('content')
    @php $saldoRestante = $pagamento->saldoRestante(); @endphp

    {{-- Dados do Pagamento --}}
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Pagamento #{{ $pagamento->id }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Cliente:</strong> {{ $pagamento->cliente->nome ?? '-' }}</div>
                <div class="col-md-4 mb-3"><strong>Forma:</strong> {{ ucfirst($pagamento->forma_pagamento->value) }}</div>
                <div class="col-md-4 mb-3">
                    <strong>Status:</strong>
                    @switch($pagamento->status->value)
                        @case('pago')
                            <span class="badge bg-success">Pago</span>
                            @break
                        @case('pendente')
                            <span class="badge bg-warning">Pendente</span>
                            @break
                        @case('cancelado')
                            <span class="badge bg-danger">Cancelado</span>
                            @break
                        @case('estornado')
                            <span class="badge bg-secondary">Estornado</span>
                            @break
                    @endswitch
                </div>
                <div class="col-md-4 mb-3"><strong>Valor Total:</strong> R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</div>
                <div class="col-md-4 mb-3"><strong>Valor Pago:</strong> R$ {{ number_format($pagamento->valor_pago, 2, ',', '.') }}</div>
                <div class="col-md-4 mb-3">
                    <strong>Saldo Restante:</strong>
                    <span class="{{ $saldoRestante > 0 ? 'text-danger fw-bold' : 'text-success' }}">
                        R$ {{ number_format($saldoRestante, 2, ',', '.') }}
                    </span>
                </div>

                {{-- Origem --}}
                @if($pagamento->agendamento)
                <div class="col-md-6 mb-3">
                    <strong>Origem:</strong> Agendamento #{{ $pagamento->agendamento->id }}
                    — {{ $pagamento->agendamento->servico->nome ?? '' }}
                    ({{ \Carbon\Carbon::parse($pagamento->agendamento->inicio)->format('d/m/Y H:i') }})
                </div>
                @elseif($pagamento->vendaPacote)
                <div class="col-md-6 mb-3">
                    <strong>Origem:</strong> Pacote #{{ $pagamento->vendaPacote->id }}
                    — {{ $pagamento->vendaPacote->servico->nome ?? '' }}
                    ({{ $pagamento->vendaPacote->qtd_sessoes }} sessões)
                </div>
                @elseif($pagamento->vendaProduto)
                <div class="col-md-6 mb-3">
                    <strong>Origem:</strong> Venda Produto #{{ $pagamento->vendaProduto->id }}
                    — {{ $pagamento->vendaProduto->itens->pluck('descricao')->implode(', ') }}
                </div>
                @endif

                <div class="col-md-6 mb-3"><strong>Data:</strong> {{ $pagamento->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    {{-- Botao Registrar Baixa --}}
    @if($pagamento->status->value === 'pendente' && $saldoRestante > 0)
    <div class="row mb-4">
        <div class="col-12">
            <button type="button" class="btn btn-primary" id="btn-baixa">
                <i class="feather-dollar-sign me-2"></i>Registrar Pagamento
            </button>
        </div>
    </div>

    <form id="form-baixa" action="{{ route('pagamentos.baixa', $pagamento) }}" method="POST" style="display:none;">
        @csrf
        <input type="hidden" name="valor" id="baixa-valor">
        <input type="hidden" name="forma_pagamento" id="baixa-forma">
        <input type="hidden" name="observacao" id="baixa-obs">
    </form>
    @endif

    {{-- Historico de Baixas --}}
    @if($pagamento->baixas->count() > 0)
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Histórico de Pagamentos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Forma</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pagamento->baixas->sortByDesc('data') as $baixa)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($baixa->data)->format('d/m/Y H:i') }}</td>
                            <td>R$ {{ number_format($baixa->valor, 2, ',', '.') }}</td>
                            <td>{{ ucfirst($baixa->forma_pagamento->value) }}</td>
                            <td>{{ $baixa->observacao ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="d-flex gap-2 text-center pt-4">
        <a href="{{ route('pagamentos.index') }}" class="btn btn-light px-5 py-2" style="min-width: 300px;">
            <i class="feather-arrow-left me-2"></i>
            <span>Voltar</span>
        </a>
    </div>
@endsection

@push('js')
@if($pagamento->status->value === 'pendente' && $saldoRestante > 0)
<script>
document.getElementById('btn-baixa').addEventListener('click', function() {
    Swal.fire({
        title: 'Registrar Pagamento',
        html: '<label class="swal2-input-label">Valor (R$)</label>' +
              '<input id="swal-valor" class="swal2-input" type="number" step="0.01" min="0.01" max="{{ $saldoRestante }}" value="{{ number_format($saldoRestante, 2, '.', '') }}" style="width:100%;max-width:100%;box-sizing:border-box;">' +
              '<label class="swal2-input-label">Forma de Pagamento</label>' +
              '<select id="swal-forma" class="swal2-input" style="width:100%;max-width:100%;box-sizing:border-box;">' +
              '<option value="pix">Pix</option>' +
              '<option value="dinheiro">Dinheiro</option>' +
              '<option value="cartao">Cartão</option>' +
              '</select>' +
              '<textarea id="swal-obs" class="swal2-input" rows="2" placeholder="Observação (opcional)" style="width:100%;max-width:100%;height:auto;box-sizing:border-box;"></textarea>',
        showCancelButton: true,
        confirmButtonText: 'Registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3454d1',
        preConfirm: function() {
            var valor = document.getElementById('swal-valor').value;
            var forma = document.getElementById('swal-forma').value;
            if (!valor || parseFloat(valor) <= 0) {
                Swal.showValidationMessage('Informe um valor válido');
                return false;
            }
            if (parseFloat(valor) > {{ $saldoRestante }}) {
                Swal.showValidationMessage('Valor excede o saldo restante');
                return false;
            }
            return { valor: valor, forma: forma, obs: document.getElementById('swal-obs').value };
        }
    }).then(function(result) {
        if (result.value) {
            document.getElementById('baixa-valor').value = result.value.valor;
            document.getElementById('baixa-forma').value = result.value.forma;
            document.getElementById('baixa-obs').value = result.value.obs;
            document.getElementById('form-baixa').submit();
        }
    });
});
</script>
@endif
@endpush
