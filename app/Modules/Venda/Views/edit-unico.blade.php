@extends('layouts.app')

@section('titulo', 'Editar Agendamento - Meu Negócio')
@section('titulo-pagina', 'Editar Agendamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Editar agendamento</li>
@endsection

@section('content')
    <form action="{{ route('vendas.update-unico', $agendamento) }}" method="POST">
        @csrf @method('PATCH')

        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Agendamento #{{ $agendamento->id }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <input type="text" id="clienteSearch" class="form-control @error('cliente_id') is-invalid @enderror" placeholder="Digite o nome ou telefone..." autocomplete="off" value="{{ old('_cliente_nome', $agendamento->cliente->nome ?? '') }}">
                        <input type="hidden" name="cliente_id" id="clienteHidden" value="{{ old('cliente_id', $agendamento->cliente_id) }}">
                        @error('cliente_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Serviço</label>
                        <input type="text" class="form-control" value="{{ $agendamento->servico->nome ?? '—' }}" disabled>
                        <div class="fs-12 text-muted mt-1">Para alterar serviço, data/horário ou atendente, use <a href="{{ route('agenda.edit', $agendamento) }}">edição do agendamento</a>.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observações</label>
                        <textarea name="observacao" rows="4" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao', $agendamento->observacoes) }}</textarea>
                        @error('observacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('vendas.index')" />
    </form>
@endsection

@push('js')
<script>
    initAjaxSearch({
        inputId: 'clienteSearch',
        hiddenId: 'clienteHidden',
        url: '{{ route("clientes.buscar") }}',
        renderItem: function(item) {
            return '<strong>' + item.nome + '</strong>' + (item.telefone ? '<br><small class="text-muted">' + item.telefone + '</small>' : '');
        },
        displayText: function(item) { return item.nome; },
    });
</script>
@endpush
