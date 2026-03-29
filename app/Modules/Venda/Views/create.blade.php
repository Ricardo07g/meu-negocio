@extends('layouts.app')

@section('titulo', 'Nova Venda - Meu Negócio')
@section('titulo-pagina', 'Nova Venda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    <form action="{{ route('vendas.store') }}" method="POST" id="formNovaVenda">
        @csrf
        <input type="hidden" name="tipo_venda" id="tipoVendaInput" value="{{ old('tipo_venda', 'servico') }}">

        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Nova Venda</h5>
            </div>
            <div class="card-body">
                {{-- Toggle Serviço / Produto --}}
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Venda</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-toggle {{ old('tipo_venda', 'servico') === 'servico' ? 'btn-primary' : 'btn-outline-primary' }}" data-tipo-venda="servico">
                                <i class="feather-briefcase me-1"></i> Serviço
                            </button>
                            <button type="button" class="btn btn-toggle {{ old('tipo_venda') === 'produto' ? 'btn-primary' : 'btn-outline-primary' }}" data-tipo-venda="produto">
                                <i class="feather-package me-1"></i> Produto
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="cliente_id" id="clienteSelect" class="form-select @error('cliente_id') is-invalid @enderror">
                            <option value="">Selecione...</option>
                            @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>{{ $cliente->nome }}</option>
                            @endforeach
                        </select>
                        @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- ===== CAMPOS DE SERVIÇO ===== --}}
                <div id="campos-servico" style="{{ old('tipo_venda') === 'produto' ? 'display:none;' : '' }}">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Serviço <span class="text-danger">*</span></label>
                            <select name="servico_id" id="servicoSelect" class="form-select @error('servico_id') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                @foreach($servicos as $servico)
                                <option value="{{ $servico->id }}"
                                    data-tipo="{{ $servico->tipo->value }}"
                                    data-duracao="{{ $servico->duracao }}"
                                    data-valor="{{ $servico->valor }}"
                                    data-qtd-sessoes="{{ $servico->qtd_sessoes }}"
                                    {{ old('servico_id') == $servico->id ? 'selected' : '' }}>
                                    {{ $servico->nome }}
                                </option>
                                @endforeach
                            </select>
                            @error('servico_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Atendente <span class="text-danger">*</span></label>
                            <select name="atendente_id" id="atendenteSelect" class="form-select @error('atendente_id') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                @foreach($atendentes as $atendente)
                                <option value="{{ $atendente->id }}" {{ old('atendente_id') == $atendente->id ? 'selected' : '' }}>{{ $atendente->nome }}</option>
                                @endforeach
                            </select>
                            @error('atendente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- Campos Avulso --}}
                    <div id="campos-avulso" style="display:none;">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Data/Hora Início <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="inicio" id="inicioAvulso" class="form-control @error('inicio') is-invalid @enderror" value="{{ old('inicio') }}" disabled>
                                @error('inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fim <small class="text-muted">(opcional)</small></label>
                                <input type="datetime-local" name="fim" id="fimAvulso" class="form-control @error('fim') is-invalid @enderror" value="{{ old('fim') }}" disabled>
                                @error('fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        {{-- botões no final do form --}}
                    </div>

                    {{-- Campos Pacote --}}
                    <div id="campos-pacote" style="display:none;">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Data Início <span class="text-danger">*</span></label>
                                <input type="date" id="dataInicio" class="form-control" value="{{ old('data_inicio', now()->format('Y-m-d')) }}" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Horário <span class="text-danger">*</span></label>
                                <input type="time" name="horario" id="horario" class="form-control @error('horario') is-invalid @enderror" value="{{ old('horario', '09:00') }}" disabled>
                                @error('horario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Qtd Sessões <span class="text-danger">*</span></label>
                                <input type="number" id="qtdSessoesInput" class="form-control" value="{{ old('qtd_sessoes') }}" min="2" disabled>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="form-label">Dias da Semana <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    @php $diasNomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; @endphp
                                    @foreach($diasNomes as $i => $dia)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input dias-semana-check" value="{{ $i }}"
                                            id="dia{{ $i }}" {{ in_array($i, old('dias_semana', [])) ? 'checked' : '' }} disabled>
                                        <label class="form-check-label" for="dia{{ $i }}">{{ $dia }}</label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Valor Total (R$) <span class="text-danger">*</span></label>
                                <input type="number" name="valor_total" id="valorTotal" class="form-control @error('valor_total') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('valor_total') }}" disabled>
                                @error('valor_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <span id="valorPorSessao" class="text-muted fs-13"></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" id="btnGerarPreview" class="btn btn-outline-primary">
                                <i class="feather-calendar me-1"></i> Gerar Preview das Sessões
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ===== CAMPOS DE PRODUTO ===== --}}
                <div id="campos-produto" style="{{ old('tipo_venda', 'servico') === 'servico' ? 'display:none;' : '' }}">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Produto <span class="text-danger">*</span></label>
                            <select name="produto_id" id="produtoSelect" class="form-select @error('produto_id') is-invalid @enderror" disabled>
                                <option value="">Selecione...</option>
                                @foreach($produtos as $produto)
                                <option value="{{ $produto->id }}"
                                    data-valor="{{ $produto->valor_venda }}"
                                    data-estoque="{{ $produto->quantidade }}"
                                    {{ old('produto_id') == $produto->id ? 'selected' : '' }}>
                                    {{ $produto->nome }} ({{ $produto->quantidade }} em estoque)
                                </option>
                                @endforeach
                            </select>
                            @error('produto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="quantidadeProduto" class="form-control @error('quantidade') is-invalid @enderror" value="{{ old('quantidade', 1) }}" min="1" disabled>
                            @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor Total (R$) <span class="text-danger">*</span></label>
                            <input type="number" name="valor_total" id="valorTotalProduto" class="form-control @error('valor_total') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('valor_total') }}" disabled>
                            @error('valor_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    {{-- botões no final do form --}}
                </div>
            </div>
        </div>

        {{-- Pagamento --}}
        <div class="card stretch stretch-full mt-4">
            <div class="card-header">
                <h5 class="card-title">Pagamento</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach(['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão', 'fiado' => 'Fiado'] as $val => $label)
                            <option value="{{ $val }}" {{ old('forma_pagamento') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status do Pagamento <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="status_pagamento" id="statusPago" value="pago" {{ old('status_pagamento', 'pago') === 'pago' ? 'checked' : '' }}>
                            <label class="btn btn-outline-success" for="statusPago">Pago</label>
                            <input type="radio" class="btn-check" name="status_pagamento" id="statusPendente" value="pendente" {{ old('status_pagamento') === 'pendente' ? 'checked' : '' }}>
                            <label class="btn btn-outline-warning" for="statusPendente">Pendente</label>
                        </div>
                        @error('status_pagamento') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview das sessoes (pacote) --}}
        <div class="card stretch stretch-full mt-4" id="previewCard" style="display:none;">
            <div class="card-header">
                <h5 class="card-title">Preview das Sessões <span id="qtdSessoesBadge" class="badge bg-primary ms-2"></span></h5>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13 mb-3">Você pode editar as datas individualmente antes de salvar.</p>
                <div class="table-responsive">
                    <table class="table table-hover" id="tabelaSessoes">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Dia da Semana</th>
                                <th>Data</th>
                                <th>Horário</th>
                            </tr>
                        </thead>
                        <tbody id="sessoesTbody"></tbody>
                    </table>
                </div>
                {{-- botões no final do form --}}
            </div>
        </div>

        <x-form-botoes :voltar="route('vendas.index')" />
    </form>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const tipoVendaInput = document.getElementById('tipoVendaInput');
    const camposServico = document.getElementById('campos-servico');
    const camposProduto = document.getElementById('campos-produto');
    const camposAvulso = document.getElementById('campos-avulso');
    const camposPacote = document.getElementById('campos-pacote');
    const previewCard = document.getElementById('previewCard');
    const servicoSelect = document.getElementById('servicoSelect');
    const produtoSelect = document.getElementById('produtoSelect');
    const clienteSelect = document.getElementById('clienteSelect');

    // Toggle Serviço / Produto
    document.querySelectorAll('.btn-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipoVenda;
            tipoVendaInput.value = tipo;

            document.querySelectorAll('.btn-toggle').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-primary');
            });
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');

            if (tipo === 'servico') {
                camposServico.style.display = 'block';
                camposProduto.style.display = 'none';
                habilitarContainer(camposProduto, false);
                servicoSelect.dispatchEvent(new Event('change'));
            } else {
                camposServico.style.display = 'none';
                camposProduto.style.display = 'block';
                habilitarContainer(camposAvulso, false);
                habilitarContainer(camposPacote, false);
                habilitarContainer(camposProduto, true);
                previewCard.style.display = 'none';
            }
        });
    });

    // Toggle Avulso / Pacote conforme tipo do servico
    servicoSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const tipo = opt.dataset ? (opt.dataset.tipo || '') : '';

        if (tipo === 'avulso') {
            camposAvulso.style.display = 'block';
            camposPacote.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposAvulso, true);
            habilitarContainer(camposPacote, false);
        } else if (tipo === 'pacote') {
            camposAvulso.style.display = 'none';
            camposPacote.style.display = 'block';
            habilitarContainer(camposAvulso, false);
            habilitarContainer(camposPacote, true);
            // Pre-preencher qtd_sessoes e valor do servico
            const qtdSessoesInput = document.getElementById('qtdSessoesInput');
            const valorTotal = document.getElementById('valorTotal');
            if (opt.dataset.qtdSessoes && !qtdSessoesInput.value) {
                qtdSessoesInput.value = opt.dataset.qtdSessoes;
            }
            if (opt.dataset.valor) {
                valorTotal.value = parseFloat(opt.dataset.valor).toFixed(2);
            }
            atualizarValorPorSessao();
        } else {
            camposAvulso.style.display = 'none';
            camposPacote.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposAvulso, false);
            habilitarContainer(camposPacote, false);
        }
    });

    function habilitarContainer(container, habilitar) {
        container.querySelectorAll('input, select').forEach(function(el) {
            el.disabled = !habilitar;
        });
    }

    // Produto: auto-calcular valor
    produtoSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.dataset.valor) {
            const qtd = parseInt(document.getElementById('quantidadeProduto').value) || 1;
            document.getElementById('valorTotalProduto').value = (parseFloat(opt.dataset.valor) * qtd).toFixed(2);
        }
    });
    document.getElementById('quantidadeProduto').addEventListener('input', function() {
        const opt = produtoSelect.options[produtoSelect.selectedIndex];
        if (opt && opt.dataset.valor) {
            document.getElementById('valorTotalProduto').value = (parseFloat(opt.dataset.valor) * (parseInt(this.value) || 1)).toFixed(2);
        }
    });

    // Valor por sessão (pacote)
    const valorTotal = document.getElementById('valorTotal');
    const qtdSessoesInput = document.getElementById('qtdSessoesInput');
    const valorPorSessao = document.getElementById('valorPorSessao');

    valorTotal.addEventListener('input', atualizarValorPorSessao);
    qtdSessoesInput.addEventListener('input', atualizarValorPorSessao);

    function atualizarValorPorSessao() {
        const qtd = parseInt(qtdSessoesInput.value) || 0;
        const val = parseFloat(valorTotal.value) || 0;
        if (qtd > 0 && val > 0) {
            valorPorSessao.textContent = 'R$ ' + (val / qtd).toFixed(2).replace('.', ',') + ' por sessão';
        } else {
            valorPorSessao.textContent = '';
        }
    }

    // Gerar preview das sessoes (pacote)
    document.getElementById('btnGerarPreview').addEventListener('click', function() {
        const opt = servicoSelect.options[servicoSelect.selectedIndex];
        const dataInicio = document.getElementById('dataInicio');
        const horario = document.getElementById('horario');

        if (!opt || !opt.value) { swalAlerta('Selecione um serviço.'); return; }
        if (!dataInicio.value) { swalAlerta('Informe a data de início.'); return; }

        const qtdSessoes = parseInt(qtdSessoesInput.value);
        if (!qtdSessoes || qtdSessoes < 2) { swalAlerta('Informe a quantidade de sessões (mínimo 2).'); qtdSessoesInput.focus(); return; }

        const diasSemana = [];
        document.querySelectorAll('.dias-semana-check:checked').forEach(cb => diasSemana.push(parseInt(cb.value)));
        if (diasSemana.length === 0) { swalAlerta('Selecione pelo menos um dia da semana.'); return; }

        const duracao = parseInt(opt.dataset.duracao);
        const inicio = new Date(dataInicio.value + 'T12:00:00');
        const datas = [];
        let cursor = new Date(inicio);
        let seguranca = 0;

        while (datas.length < qtdSessoes && seguranca < 365) {
            if (diasSemana.includes(cursor.getDay())) datas.push(new Date(cursor));
            cursor.setDate(cursor.getDate() + 1);
            seguranca++;
        }

        const tbody = document.getElementById('sessoesTbody');
        tbody.innerHTML = '';
        datas.forEach((data, i) => {
            const dataStr = data.toISOString().split('T')[0];
            const tr = document.createElement('tr');
            const fh = horario.value.split(':').map(Number);
            const totalMin = fh[0] * 60 + fh[1] + duracao;
            const fim = String(Math.floor(totalMin / 60) % 24).padStart(2, '0') + ':' + String(totalMin % 60).padStart(2, '0');
            tr.innerHTML = `<td>${i + 1}</td><td>${diasNomes[data.getDay()]}</td><td><input type="date" name="datas[]" value="${dataStr}" class="form-control form-control-sm" required></td><td><input type="time" name="horarios[]" value="${horario.value}" class="form-control form-control-sm" required></td>`;
            tbody.appendChild(tr);
        });

        document.getElementById('qtdSessoesBadge').textContent = datas.length + ' sessões';
        previewCard.style.display = 'block';
        previewCard.scrollIntoView({ behavior: 'smooth' });
        atualizarValorPorSessao();
    });

    // Inicializar estado
    if (tipoVendaInput.value === 'produto') {
        habilitarContainer(camposProduto, true);
    } else if (servicoSelect.value) {
        servicoSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
