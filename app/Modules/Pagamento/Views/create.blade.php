@extends('layouts.app')

@section('titulo', 'Novo Pagamento - Meu Negócio')
@section('titulo-pagina', 'Novo Pagamento')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pagamentos.index') }}">Pagamentos</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title">Registrar Pagamento</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('pagamentos.store') }}" method="POST">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach(['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão', 'fiado' => 'Fiado'] as $valor => $label)
                                <option value="{{ $valor }}" {{ old('forma_pagamento') == $valor ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Agendamento <small class="text-muted">(opcional)</small></label>
                        <select name="agendamento_id" class="form-select @error('agendamento_id') is-invalid @enderror">
                            <option value="">Nenhum</option>
                            @if(isset($agendamentos))
                            @foreach($agendamentos as $agendamento)
                                <option value="{{ $agendamento->id }}" {{ old('agendamento_id') == $agendamento->id ? 'selected' : '' }}>
                                    #{{ $agendamento->id }} - {{ $agendamento->cliente->nome ?? 'N/A' }} ({{ \Carbon\Carbon::parse($agendamento->inicio)->format('d/m/Y H:i') }})
                                </option>
                            @endforeach
                            @endif
                        </select>
                        @error('agendamento_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('pagamentos.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
