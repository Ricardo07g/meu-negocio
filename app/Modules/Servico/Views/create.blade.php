@extends('layouts.app')

@section('titulo', 'Novo Serviço - Meu Negócio')
@section('titulo-pagina', 'Novo Serviço')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('servicos.index') }}">Serviços</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('servicos.store') }}" method="POST">
        @csrf
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Cadastrar Serviço</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" id="tipoServico" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="avulso" {{ old('tipo', 'avulso') === 'avulso' ? 'selected' : '' }}>Avulso</option>
                            <option value="pacote" {{ old('tipo') === 'pacote' ? 'selected' : '' }}>Pacote</option>
                        </select>
                        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duração por sessão (min) <span class="text-danger">*</span></label>
                        <input type="number" name="duracao" class="form-control @error('duracao') is-invalid @enderror" value="{{ old('duracao') }}" min="1" required>
                        @error('duracao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" step="0.01" min="0" required>
                        @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Campos de pacote --}}
                <div id="camposPacote" style="{{ old('tipo') === 'pacote' ? '' : 'display:none;' }}">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Quantidade de Sessões <span class="text-danger">*</span></label>
                            <input type="number" name="qtd_sessoes" class="form-control @error('qtd_sessoes') is-invalid @enderror" value="{{ old('qtd_sessoes') }}" min="2">
                            @error('qtd_sessoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Descrição do Pacote</label>
                            <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" placeholder="Ex: 10 sessões de massagem relaxante">
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

