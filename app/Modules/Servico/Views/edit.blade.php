@extends('layouts.app')

@section('titulo', 'Editar Serviço - Meu Negócio')
@section('titulo-pagina', 'Editar Serviço')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <form action="{{ route('servicos.update', $servico) }}" method="POST">
        @csrf @method('PUT')
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Editar Serviço</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" id="tipoServico" class="form-select" required>
                            <option value="avulso" {{ old('tipo', $servico->tipo->value) === 'avulso' ? 'selected' : '' }}>Avulso</option>
                            <option value="pacote" {{ old('tipo', $servico->tipo->value) === 'pacote' ? 'selected' : '' }}>Pacote</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $servico->nome) }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duração por sessão (min) <span class="text-danger">*</span></label>
                        <input type="number" name="duracao" class="form-control @error('duracao') is-invalid @enderror" value="{{ old('duracao', $servico->duracao) }}" min="1" required>
                        @error('duracao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor', $servico->valor) }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Campos de pacote --}}
                @php $tipoAtual = old('tipo', $servico->tipo->value); @endphp
                <div id="camposPacote" style="{{ $tipoAtual === 'pacote' ? '' : 'display:none;' }}">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Quantidade de Sessões <span class="text-danger">*</span></label>
                            <input type="number" name="qtd_sessoes" class="form-control @error('qtd_sessoes') is-invalid @enderror" value="{{ old('qtd_sessoes', $servico->qtd_sessoes) }}" min="2">
                            @error('qtd_sessoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Descrição do Pacote</label>
                            <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $servico->descricao) }}" placeholder="Ex: 10 sessões de massagem relaxante">
                            @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-form-botoes :voltar="route('servicos.index')" />
    </form>
@endsection

@push('js')
<script>
document.getElementById('tipoServico').addEventListener('change', function() {
    document.getElementById('camposPacote').style.display = this.value === 'pacote' ? '' : 'none';
});
</script>
@endpush

