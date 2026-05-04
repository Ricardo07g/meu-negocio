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
                        <div>
                            <input type="text" id="clienteSearch" class="form-control @error('cliente_id') is-invalid @enderror" placeholder="Digite o nome ou telefone do cliente..." autocomplete="off" value="{{ $clienteOld->nome ?? '' }}">
                            <input type="hidden" name="cliente_id" id="clienteHidden" value="{{ old('cliente_id') }}">
                        </div>
                        @error('cliente_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- ===== CAMPOS DE SERVIÇO ===== --}}
                <div id="campos-servico" style="{{ old('tipo_venda') === 'produto' ? 'display:none;' : '' }}">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Serviço <span class="text-danger">*</span></label>
                            <div>
                                <input type="text" id="servicoSearch" class="form-control @error('servico_id') is-invalid @enderror" placeholder="Digite o nome do serviço..." autocomplete="off" value="{{ $servicoOld->nome ?? '' }}">
                                <input type="hidden" name="servico_id" id="servicoHidden" value="{{ old('servico_id') }}">
                            </div>
                            @error('servico_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
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
                                <label class="form-label">Data <span class="text-danger">*</span></label>
                                <input type="date" name="data" id="dataAvulso" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', now()->format('Y-m-d')) }}" disabled>
                                @error('data') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Horário <span class="text-danger">*</span></label>
                                <input type="time" name="horario" id="horarioAvulso" class="form-control @error('horario') is-invalid @enderror" value="{{ old('horario', '09:00') }}" disabled>
                                @error('horario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end pb-2">
                                <span id="fimCalculadoAvulso" class="text-muted fs-13"></span>
                            </div>
                        </div>
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

                {{-- ===== CAMPOS DE PRODUTO (CARRINHO) ===== --}}
                <div id="campos-produto" style="{{ old('tipo_venda', 'servico') === 'servico' ? 'display:none;' : '' }}">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Data da Venda</label>
                            <input type="date" name="data" id="dataVendaProduto" class="form-control" value="{{ old('data', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" disabled>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Observação</label>
                            <input type="text" name="observacao" class="form-control" placeholder="Observação da venda (opcional)" value="{{ old('observacao') }}" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card Produtos e Serviços (visível quando tipo=produto) --}}
        <div class="card stretch stretch-full mt-4" id="card-carrinho" style="{{ old('tipo_venda', 'servico') === 'servico' ? 'display:none;' : '' }}">
            <div class="card-header">
                <h5 class="card-title">Produtos e Serviços</h5>
            </div>
            <div class="card-body">
                {{-- Linha de inclusão: Produto + Qtd + Preço + Botão --}}
                <div class="row mb-4 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Produto</label>
                        <div>
                            <input type="text" id="produtoSearch" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" disabled>
                            <input type="hidden" id="produtoHidden" value="">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="produtoQtd" class="form-control" value="1" min="1" disabled>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Preço Venda</label>
                        <input type="number" id="produtoPreco" class="form-control" step="0.01" min="0" placeholder="0,00" disabled>
                    </div>
                    <div class="col-md-4">
                        <button type="button" id="btnAdicionarProduto" class="btn btn-outline-primary w-100" disabled>
                            <i class="feather-shopping-cart me-1"></i> Incluir Item
                        </button>
                    </div>
                </div>

                {{-- Tabela de itens --}}
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="tabelaCarrinho">
                        <thead>
                            <tr>
                                <th style="width:40%;">Produto</th>
                                <th class="text-center" style="width:7%;">Qtd</th>
                                <th class="text-end" style="width:11%;">Vl. Unit.</th>
                                <th class="text-end" style="width:11%;">Desconto</th>
                                <th class="text-end" style="width:11%;">Acréscimo</th>
                                <th class="text-end" style="width:13%;">Vl. Total</th>
                                <th class="text-center" style="width:7%;"></th>
                            </tr>
                        </thead>
                        <tbody id="carrinhoTbody">
                            <tr id="carrinhoVazio"><td colspan="7" class="text-center text-muted py-4">Nenhum item adicionado.</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- Resumo financeiro --}}
                <div class="row mt-4 pt-3" id="carrinhoResumo" style="display:none; border-top: 1px solid #eee;">
                    <div class="col-md-5 offset-md-7 col-lg-4 offset-lg-8">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-end text-muted">Subtotal:</td>
                                <td class="text-end" id="carrinhoSubtotal">R$ 0,00</td>
                            </tr>
                            <tr>
                                <td class="text-end text-danger">Descontos:</td>
                                <td class="text-end text-danger" id="carrinhoDescontos">R$ 0,00</td>
                            </tr>
                            <tr>
                                <td class="text-end text-success">Acréscimos:</td>
                                <td class="text-end text-success" id="carrinhoAcrescimos">R$ 0,00</td>
                            </tr>
                            <tr class="fw-bold fs-5">
                                <td class="text-end">Total:</td>
                                <td class="text-end text-success" id="carrinhoTotal">R$ 0,00</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden inputs montados pelo JS antes do submit --}}
        <div id="carrinhoHiddenInputs"></div>

        {{-- Pagamento --}}
        <div class="card stretch stretch-full mt-4" id="cardPagamento">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Pagamento</h5>
                <span id="pagamentoAvisoInline" class="badge bg-soft-warning text-warning" style="display:none;">
                    <i class="feather-alert-circle me-1"></i><span id="pagamentoAvisoTexto"></span>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php $condAtual = old('condicao_pagamento', 'a_vista'); @endphp
                    <div class="col-md-4">
                        <label class="form-label">Condição de Pagamento <span class="text-danger">*</span></label>
                        <select name="condicao_pagamento" id="condicaoPagamentoSelect" class="form-select @error('condicao_pagamento') is-invalid @enderror">
                            <option value="a_vista" @selected($condAtual === 'a_vista')>À Vista</option>
                            <option value="a_prazo" @selected($condAtual === 'a_prazo')>A Prazo</option>
                        </select>
                        @error('condicao_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4" id="formaPagamentoWrapper">
                        <label class="form-label" id="formaPagamentoLabel">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" id="formaPagamentoSelect"
                                class="form-select @error('forma_pagamento') is-invalid @enderror"
                                data-old="{{ old('forma_pagamento') }}">
                            <option value="">Selecione...</option>
                            {{-- Opcoes preenchidas dinamicamente pelo JS conforme a condicao de pagamento --}}
                        </select>
                        @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4" id="formaRecebimentoPrazoWrapper" style="display:none;">
                        <label class="form-label" for="formaRecebimentoPrazoSelect">
                            Forma de Recebimento <span class="text-danger">*</span>
                            <x-label-info content="Como as parcelas serão cobradas do cliente.<br><b>Carnê</b>: controle manual — cada parcela é recebida e baixada aqui no sistema.<br><br>Novas formas (boleto registrado, Pix parcelado) serão adicionadas no futuro." />
                        </label>
                        <select name="forma_recebimento_prazo" id="formaRecebimentoPrazoSelect"
                                class="form-select @error('forma_recebimento_prazo') is-invalid @enderror">
                            @foreach(\App\Enums\FormaRecebimentoPrazo::cases() as $f)
                                <option value="{{ $f->value }}" {{ old('forma_recebimento_prazo', 'carne') === $f->value ? 'selected' : '' }}>{{ $f->label() }}</option>
                            @endforeach
                        </select>
                        @error('forma_recebimento_prazo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4" id="parcelasWrapper" style="display:none;">
                        <label class="form-label" for="numeroParcelas">Número de Parcelas <span class="text-danger">*</span></label>
                        <input type="number" min="2" max="24" step="1" name="numero_parcelas" id="numeroParcelas"
                               class="form-control @error('numero_parcelas') is-invalid @enderror"
                               value="{{ old('numero_parcelas', 2) }}">
                        <div class="form-text" id="valorPorParcelaHint"></div>
                        @error('numero_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4" id="primeiroVencimentoWrapper" style="display:none;">
                        <label class="form-label" for="primeiroVencimento">Primeiro Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="primeiro_vencimento" id="primeiroVencimento"
                               class="form-control @error('primeiro_vencimento') is-invalid @enderror"
                               value="{{ old('primeiro_vencimento', now()->addDays(30)->format('Y-m-d')) }}"
                               min="{{ now()->format('Y-m-d') }}">
                        @error('primeiro_vencimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="mesReferencia">Mês de Referência <span class="text-danger">*</span></label>
                        <input type="month" name="mes_referencia" id="mesReferencia"
                               class="form-control @error('mes_referencia') is-invalid @enderror"
                               value="{{ old('mes_referencia', now()->format('Y-m')) }}" required>
                        <div class="form-text">Competência contábil da venda.</div>
                        @error('mes_referencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12" id="parceladoAviso" style="display:none;">
                        <small class="text-muted">
                            <i class="feather-info me-1"></i>
                            A venda entra em <strong>Contas a Receber</strong> com as parcelas listadas abaixo.
                            Cada parcela é recebida e baixada individualmente.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview das sessoes (pacote) — acima do preview das parcelas --}}
        <div class="card stretch stretch-full mt-4" id="previewCard" style="display:none;">
            <div class="card-header">
                <h5 class="card-title">Preview das Sessões <span id="qtdSessoesBadge" class="badge bg-primary ms-2"></span></h5>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13 mb-3">Você pode editar as datas individualmente antes de salvar.</p>
                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-hover" id="tabelaSessoes">
                        <thead class="position-sticky top-0 bg-white" style="z-index:1;">
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
            </div>
        </div>

        {{-- Preview das parcelas (só quando condicao=a_prazo) --}}
        <div class="card stretch stretch-full mt-4" id="previewCarneCard" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Preview das Parcelas <span id="previewCarneBadge" class="badge bg-warning ms-2"></span></h5>
                <span id="carneDiferencaBadge" class="badge bg-soft-danger text-danger" style="display:none;">
                    <i class="feather-alert-circle me-1"></i><span id="carneDiferencaTexto"></span>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13 mb-3" id="previewCarneInfo">
                    Ajuste valor, vencimento e competência de cada parcela se necessário. A soma precisa bater com o total da venda.
                </p>
                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-hover mb-0 align-middle" id="tabelaCarne">
                        <thead class="position-sticky top-0 bg-white" style="z-index:1;">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Vencimento</th>
                                <th>Competência</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="carneTbody"></tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total:</td>
                                <td class="text-end" id="carneTotalFoot">R$ 0,00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
    const cardCarrinho = document.getElementById('card-carrinho');

    // Itens selecionados via AJAX (armazenados temporariamente)
    var produtoSelecionado = null;
    var servicoSelecionado = null;

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
                cardCarrinho.style.display = 'none';
                habilitarContainer(camposProduto, false);
                habilitarContainer(cardCarrinho, false);
                aplicarTipoServico(servicoSelecionado);
            } else {
                camposServico.style.display = 'none';
                camposProduto.style.display = 'block';
                cardCarrinho.style.display = 'block';
                habilitarContainer(camposAvulso, false);
                habilitarContainer(camposPacote, false);
                habilitarContainer(camposProduto, true);
                habilitarContainer(cardCarrinho, true);
                previewCard.style.display = 'none';
            }
            if (typeof atualizarHabilitacaoPagamento === 'function') atualizarHabilitacaoPagamento();
        });
    });

    // Toggle Avulso / Pacote conforme tipo do servico selecionado
    function aplicarTipoServico(servico) {
        if (!servico) {
            camposAvulso.style.display = 'none';
            camposPacote.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposAvulso, false);
            habilitarContainer(camposPacote, false);
            return;
        }

        var tipo = servico.tipo;
        if (typeof tipo === 'object' && tipo !== null) tipo = tipo.value || tipo;

        if (tipo === 'avulso') {
            camposAvulso.style.display = 'block';
            camposPacote.style.display = 'none';
            previewCard.style.display = 'none';
            habilitarContainer(camposAvulso, true);
            habilitarContainer(camposPacote, false);
            atualizarFimAvulso();
        } else if (tipo === 'pacote') {
            camposAvulso.style.display = 'none';
            camposPacote.style.display = 'block';
            habilitarContainer(camposAvulso, false);
            habilitarContainer(camposPacote, true);
            var qtdSessoesInput = document.getElementById('qtdSessoesInput');
            var valorTotal = document.getElementById('valorTotal');
            if (servico.qtd_sessoes && !qtdSessoesInput.value) {
                qtdSessoesInput.value = servico.qtd_sessoes;
            }
            if (servico.valor) {
                valorTotal.value = parseFloat(servico.valor).toFixed(2);
            }
            atualizarValorPorSessao();
        }
    }

    function habilitarContainer(container, habilitar) {
        container.querySelectorAll('input, select, textarea, button').forEach(function(el) {
            el.disabled = !habilitar;
        });
    }

    // ===== CARRINHO DE PRODUTOS =====
    const carrinhoItens = [];
    const carrinhoTbody = document.getElementById('carrinhoTbody');
    const carrinhoTotal = document.getElementById('carrinhoTotal');
    const carrinhoSubtotal = document.getElementById('carrinhoSubtotal');
    const carrinhoDescontos = document.getElementById('carrinhoDescontos');
    const carrinhoAcrescimos = document.getElementById('carrinhoAcrescimos');
    const carrinhoResumo = document.getElementById('carrinhoResumo');
    const carrinhoHidden = document.getElementById('carrinhoHiddenInputs');
    const btnAdicionar = document.getElementById('btnAdicionarProduto');
    const produtoQtd = document.getElementById('produtoQtd');
    const produtoPreco = document.getElementById('produtoPreco');
    const produtoSearch = document.getElementById('produtoSearch');
    const produtoHidden = document.getElementById('produtoHidden');

    btnAdicionar.addEventListener('click', function() {
        if (!produtoSelecionado) return;

        const produtoId = produtoSelecionado.id;
        const qtd = parseInt(produtoQtd.value) || 1;
        const preco = parseFloat(produtoPreco.value) || parseFloat(produtoSelecionado.valor_venda);
        const existente = carrinhoItens.find(i => i.produto_id === produtoId);

        if (existente) {
            existente.quantidade += qtd;
            existente.valor_unitario = preco;
        } else {
            carrinhoItens.push({
                produto_id: produtoId,
                nome: produtoSelecionado.nome,
                quantidade: qtd,
                valor_unitario: preco,
                desconto: 0,
                acrescimo: 0,
            });
        }

        renderCarrinho();
        produtoSearch.value = '';
        produtoHidden.value = '';
        produtoPreco.value = '';
        produtoQtd.value = 1;
        produtoSelecionado = null;
        produtoSearch.focus();
    });

    function renderCarrinho() {
        carrinhoTbody.innerHTML = '';

        if (carrinhoItens.length === 0) {
            carrinhoTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum item adicionado.</td></tr>';
            carrinhoResumo.style.display = 'none';
            return;
        }

        let subtotalGeral = 0;
        let descontoGeral = 0;
        let acrescimoGeral = 0;

        carrinhoItens.forEach(function(item, idx) {
            const itemTotal = (item.quantidade * item.valor_unitario) - item.desconto + item.acrescimo;
            subtotalGeral += item.quantidade * item.valor_unitario;
            descontoGeral += item.desconto;
            acrescimoGeral += item.acrescimo;

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + item.nome + '</td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center" value="' + item.quantidade + '" min="1" data-idx="' + idx + '" data-campo="quantidade"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.valor_unitario.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="valor_unitario"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.desconto.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="desconto"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" value="' + item.acrescimo.toFixed(2) + '" step="0.01" min="0" data-idx="' + idx + '" data-campo="acrescimo"></td>' +
                '<td class="text-end fw-bold">R$ ' + itemTotal.toFixed(2).replace('.', ',') + '</td>' +
                '<td class="text-center py-2"><button type="button" class="btn btn-sm btn-danger text-white" data-remover="' + idx + '"><i class="feather-trash-2"></i></button></td>';
            carrinhoTbody.appendChild(tr);
        });

        const totalGeral = subtotalGeral - descontoGeral + acrescimoGeral;
        carrinhoSubtotal.textContent = 'R$ ' + subtotalGeral.toFixed(2).replace('.', ',');
        carrinhoDescontos.textContent = descontoGeral > 0 ? '- R$ ' + descontoGeral.toFixed(2).replace('.', ',') : 'R$ 0,00';
        carrinhoAcrescimos.textContent = acrescimoGeral > 0 ? '+ R$ ' + acrescimoGeral.toFixed(2).replace('.', ',') : 'R$ 0,00';
        carrinhoTotal.textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
        carrinhoResumo.style.display = 'flex';

        // Eventos de edicao inline
        carrinhoTbody.querySelectorAll('input[data-campo]').forEach(function(input) {
            input.addEventListener('input', function() {
                const idx = parseInt(this.dataset.idx);
                const campo = this.dataset.campo;
                let val = parseFloat(this.value) || 0;
                if (campo === 'quantidade') val = Math.max(1, Math.round(val));
                carrinhoItens[idx][campo] = val;
                renderCarrinho();
            });
        });

        // Eventos de remover
        carrinhoTbody.querySelectorAll('button[data-remover]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                carrinhoItens.splice(parseInt(this.dataset.remover), 1);
                renderCarrinho();
            });
        });
    }

    // Montar hidden inputs antes do submit
    document.getElementById('formNovaVenda').addEventListener('submit', function() {
        carrinhoHidden.innerHTML = '';
        carrinhoItens.forEach(function(item, idx) {
            ['produto_id', 'quantidade', 'valor_unitario', 'desconto', 'acrescimo'].forEach(function(campo) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'itens[' + idx + '][' + campo + ']';
                input.value = item[campo];
                carrinhoHidden.appendChild(input);
            });
        });
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

    // Calcula e exibe o horario de termino para servico avulso
    const horarioAvulso = document.getElementById('horarioAvulso');
    const fimCalculadoAvulso = document.getElementById('fimCalculadoAvulso');

    horarioAvulso.addEventListener('input', atualizarFimAvulso);

    function atualizarFimAvulso() {
        if (!servicoSelecionado || !horarioAvulso.value) {
            fimCalculadoAvulso.textContent = '';
            return;
        }
        const duracao = parseInt(servicoSelecionado.duracao) || 0;
        if (duracao <= 0) {
            fimCalculadoAvulso.textContent = '';
            return;
        }
        const [h, m] = horarioAvulso.value.split(':').map(Number);
        const totalMin = h * 60 + m + duracao;
        const hFim = String(Math.floor(totalMin / 60) % 24).padStart(2, '0');
        const mFim = String(totalMin % 60).padStart(2, '0');
        fimCalculadoAvulso.textContent = 'Termina às ' + hFim + ':' + mFim;
    }

    // Gerar preview das sessoes (pacote)
    document.getElementById('btnGerarPreview').addEventListener('click', function() {
        const dataInicio = document.getElementById('dataInicio');
        const horario = document.getElementById('horario');

        if (!servicoSelecionado) { swalAlerta('Selecione um serviço.'); return; }
        if (!dataInicio.value) { swalAlerta('Informe a data de início.'); return; }

        const qtdSessoes = parseInt(qtdSessoesInput.value);
        if (!qtdSessoes || qtdSessoes < 2) { swalAlerta('Informe a quantidade de sessões (mínimo 2).'); qtdSessoesInput.focus(); return; }

        const diasSemana = [];
        document.querySelectorAll('.dias-semana-check:checked').forEach(cb => diasSemana.push(parseInt(cb.value)));
        if (diasSemana.length === 0) { swalAlerta('Selecione pelo menos um dia da semana.'); return; }

        const duracao = parseInt(servicoSelecionado.duracao);
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
        habilitarContainer(cardCarrinho, true);
    }

    // Restaurar estado apos erro de validacao
    @if(!empty($itensOld))
    @json($itensOld).forEach(function(item) {
        carrinhoItens.push({
            produto_id: item.produto_id,
            nome: item.nome,
            quantidade: item.quantidade,
            valor_unitario: parseFloat(item.valor_unitario),
            desconto: parseFloat(item.desconto),
            acrescimo: parseFloat(item.acrescimo),
        });
    });
    renderCarrinho();
    @endif

    @if($servicoOld ?? false)
    servicoSelecionado = {
        id: {{ $servicoOld->id }},
        nome: @json($servicoOld->nome),
        tipo: @json($servicoOld->tipo->value),
        valor: {{ $servicoOld->valor }},
        duracao: {{ $servicoOld->duracao }},
        qtd_sessoes: {{ $servicoOld->qtd_sessoes ?? 'null' }},
    };
    aplicarTipoServico(servicoSelecionado);
    @endif

    @if(!empty(old('datas')))
    (function() {
        const oldDatas = @json(old('datas', []));
        const oldHorarios = @json(old('horarios', []));
        const tbody = document.getElementById('sessoesTbody');
        tbody.innerHTML = '';
        oldDatas.forEach(function(dataStr, i) {
            const horario = oldHorarios[i] || '09:00';
            const dateObj = new Date(dataStr + 'T12:00:00');
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (i + 1) + '</td>' +
                           '<td>' + diasNomes[dateObj.getDay()] + '</td>' +
                           '<td><input type="date" name="datas[]" value="' + dataStr + '" class="form-control form-control-sm" required></td>' +
                           '<td><input type="time" name="horarios[]" value="' + horario + '" class="form-control form-control-sm" required></td>';
            tbody.appendChild(tr);
        });
        document.getElementById('qtdSessoesBadge').textContent = oldDatas.length + ' sessões';
        previewCard.style.display = 'block';
    })();
    @endif

    // Toggle Forma/Condição de Pagamento (à vista, a prazo, boleto, pix parcelado)
    const formaPagamentoWrapper = document.getElementById('formaPagamentoWrapper');
    const formaPagamentoSelect = document.getElementById('formaPagamentoSelect');
    const parcelasWrapper = document.getElementById('parcelasWrapper');
    const numeroParcelasInput = document.getElementById('numeroParcelas');
    const primeiroVencimentoWrapper = document.getElementById('primeiroVencimentoWrapper');
    const primeiroVencimento = document.getElementById('primeiroVencimento');
    const mesReferencia = document.getElementById('mesReferencia');
    const parceladoAviso = document.getElementById('parceladoAviso');
    const valorPorParcelaHint = document.getElementById('valorPorParcelaHint');

    const condicaoSelect = document.getElementById('condicaoPagamentoSelect');

    function condicaoSelecionada() {
        return condicaoSelect ? condicaoSelect.value : 'a_vista';
    }

    const previewCarneCard = document.getElementById('previewCarneCard');
    const previewCarneBadge = document.getElementById('previewCarneBadge');
    const previewCarneInfo = document.getElementById('previewCarneInfo');
    const carneTbody = document.getElementById('carneTbody');
    const carneTotalFoot = document.getElementById('carneTotalFoot');

    // Opcoes de forma_pagamento por condicao. Mantem em sincronia com FormaPagamento.cases().
    const FORMAS_AVISTA = [
        { value: 'pix', label: 'Pix' },
        { value: 'dinheiro', label: 'Dinheiro' },
        { value: 'cartao', label: 'Cartão' },
    ];
    const FORMAS_APRAZO = [
        { value: 'boleto', label: 'Boleto' },
        { value: 'pix', label: 'Pix' },
        { value: 'cartao', label: 'Cartão' },
    ];

    function popularFormasPagamento(opcoes) {
        const valorAtual = formaPagamentoSelect.value || formaPagamentoSelect.dataset.old || '';
        formaPagamentoSelect.innerHTML = '<option value="">Selecione...</option>'
            + opcoes.map(o => `<option value="${o.value}">${o.label}</option>`).join('');
        // Restaura a selecao se ainda for valida no novo conjunto.
        if (opcoes.some(o => o.value === valorAtual)) {
            formaPagamentoSelect.value = valorAtual;
        } else {
            formaPagamentoSelect.value = '';
        }
    }

    function aplicarCondicaoPagamento() {
        const c = condicaoSelecionada();
        const aVista = c === 'a_vista';
        const aPrazo = c === 'a_prazo';
        const parcelado = aPrazo;
        const exigeForma = aVista || aPrazo;

        formaPagamentoWrapper.style.display = exigeForma ? '' : 'none';
        formaPagamentoSelect.disabled = !exigeForma;
        if (!exigeForma) {
            formaPagamentoSelect.value = '';
        } else {
            popularFormasPagamento(aVista ? FORMAS_AVISTA : FORMAS_APRAZO);
        }

        const formaLabel = document.getElementById('formaPagamentoLabel');
        if (formaLabel) {
            formaLabel.innerHTML = aVista
                ? 'Recebimento à vista em <span class="text-danger">*</span>'
                : 'Forma de recebimento prevista <span class="text-danger">*</span>';
        }

        parcelasWrapper.style.display = parcelado ? '' : 'none';
        numeroParcelasInput.disabled = !parcelado;
        primeiroVencimentoWrapper.style.display = parcelado ? '' : 'none';
        primeiroVencimento.disabled = !parcelado;
        parceladoAviso.style.display = parcelado ? '' : 'none';
        previewCarneCard.style.display = parcelado ? '' : 'none';

        atualizarPreviewCarne();
    }

    function obterTotalVenda() {
        const valorTotalEl = document.getElementById('valorTotal');
        const carrinhoTotalEl = document.getElementById('carrinhoTotal');
        if (valorTotalEl && valorTotalEl.value && parseFloat(valorTotalEl.value) > 0) {
            return parseFloat(valorTotalEl.value);
        }
        if (tipoVendaInput.value === 'produto' && carrinhoTotalEl) {
            const raw = carrinhoTotalEl.textContent.replace(/[^0-9,.-]/g, '').replace('.', '').replace(',', '.');
            return parseFloat(raw) || 0;
        }
        if (servicoSelecionado && servicoSelecionado.valor) {
            return parseFloat(servicoSelecionado.valor);
        }
        return 0;
    }

    function formatarBR(v) { return v.toFixed(2).replace('.', ','); }

    function formatarDataBR(dateObj) {
        const d = String(dateObj.getDate()).padStart(2, '0');
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        return d + '/' + m + '/' + dateObj.getFullYear();
    }

    function adicionarMeses(base, meses) {
        const d = new Date(base.getFullYear(), base.getMonth() + meses, base.getDate());
        return d;
    }

    function dataIsoToMonth(iso) { return iso.substring(0, 7); }

    function recalcularSomaCarne() {
        const inputs = carneTbody.querySelectorAll('input[data-parcela-valor]');
        let soma = 0;
        inputs.forEach(function (el) { soma += parseFloat(el.value) || 0; });

        carneTotalFoot.textContent = 'R$ ' + formatarBR(soma);

        const total = obterTotalVenda();
        const diff = Math.round((soma - total) * 100) / 100;
        const badge = document.getElementById('carneDiferencaBadge');
        const badgeTxt = document.getElementById('carneDiferencaTexto');
        if (badge) {
            if (Math.abs(diff) < 0.01 || total <= 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = '';
                badgeTxt.textContent = (diff > 0 ? 'Excedendo' : 'Faltando') + ' R$ ' + formatarBR(Math.abs(diff));
            }
        }
    }

    function atualizarPreviewCarne() {
        if (condicaoSelecionada() === 'a_vista') {
            valorPorParcelaHint.textContent = '';
            previewCarneBadge.textContent = '';
            carneTbody.innerHTML = '';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }

        const n = parseInt(numeroParcelasInput.value) || 0;
        const total = obterTotalVenda();

        if (n < 2 || n > 24) {
            valorPorParcelaHint.textContent = 'Informe entre 2 e 24 parcelas.';
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe 2 a 24 parcelas.</td></tr>';
            previewCarneBadge.textContent = '';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }
        if (total <= 0) {
            valorPorParcelaHint.textContent = '';
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o valor da venda para simular as parcelas.</td></tr>';
            previewCarneBadge.textContent = n + 'x';
            carneTotalFoot.textContent = 'R$ 0,00';
            return;
        }

        const vencRaw = primeiroVencimento.value;
        if (!vencRaw) {
            carneTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Informe o primeiro vencimento.</td></tr>';
            return;
        }

        const porParcela = Math.round((total / n) * 100) / 100;
        const ultima = Math.round((total - porParcela * (n - 1)) * 100) / 100;

        valorPorParcelaHint.textContent = n + 'x de R$ ' + formatarBR(porParcela);
        previewCarneBadge.textContent = n + ' parcelas';
        previewCarneInfo.textContent =
            'Ajuste valor, vencimento e competência de cada parcela se necessário. A soma precisa bater com o total da venda.';

        const baseVenc = new Date(vencRaw + 'T12:00:00');
        const linhas = [];
        for (let i = 1; i <= n; i++) {
            const venc = adicionarMeses(baseVenc, i - 1);
            const vencIso = venc.getFullYear() + '-' +
                String(venc.getMonth() + 1).padStart(2, '0') + '-' +
                String(venc.getDate()).padStart(2, '0');
            // Competencia da parcela = mes do seu vencimento.
            const mesRefParcela = vencIso.substring(0, 7);
            const val = i === n ? ultima : porParcela;
            const idx = i - 1;
            linhas.push(
                '<tr>' +
                '<td><span class="fw-semibold">' + i + '/' + n + '</span>' +
                '<input type="hidden" name="parcelas[' + idx + '][numero]" value="' + i + '">' +
                '<input type="hidden" name="parcelas[' + idx + '][total]" value="' + n + '">' +
                '</td>' +
                '<td><input type="date" name="parcelas[' + idx + '][data_vencimento]" ' +
                '       class="form-control form-control-sm" data-parcela-vencimento value="' + vencIso + '" required></td>' +
                '<td><input type="month" name="parcelas[' + idx + '][mes_referencia]" ' +
                '       class="form-control form-control-sm" data-parcela-mes-ref value="' + mesRefParcela + '" required></td>' +
                '<td class="text-end"><input type="number" step="0.01" min="0.01" data-parcela-valor ' +
                '       name="parcelas[' + idx + '][valor]" ' +
                '       class="form-control form-control-sm text-end" value="' + val.toFixed(2) + '" required></td>' +
                '</tr>'
            );
        }
        carneTbody.innerHTML = linhas.join('');

        // Quando o usuario muda o vencimento de uma parcela manualmente, atualiza
        // a competencia daquela linha (a menos que ja tenha sido editada manual).
        carneTbody.querySelectorAll('input[data-parcela-vencimento]').forEach(function (el) {
            el.addEventListener('change', function () {
                const tr = el.closest('tr');
                const mesRefInput = tr.querySelector('input[data-parcela-mes-ref]');
                if (mesRefInput && el.value) {
                    mesRefInput.value = el.value.substring(0, 7);
                }
            });
        });

        carneTbody.querySelectorAll('input[data-parcela-valor]').forEach(function (el) {
            el.addEventListener('input', recalcularSomaCarne);
        });

        recalcularSomaCarne();
    }

    if (condicaoSelect) condicaoSelect.addEventListener('change', function() { atualizarHabilitacaoPagamento(); });
    numeroParcelasInput.addEventListener('input', atualizarPreviewCarne);
    primeiroVencimento.addEventListener('change', atualizarPreviewCarne);
    mesReferencia.addEventListener('change', atualizarPreviewCarne);
    const valorTotalEl = document.getElementById('valorTotal');
    if (valorTotalEl) valorTotalEl.addEventListener('input', function() { atualizarHabilitacaoPagamento(); });
    const carrinhoTotalObs = document.getElementById('carrinhoTotal');
    if (carrinhoTotalObs) {
        new MutationObserver(function() { atualizarHabilitacaoPagamento(); }).observe(carrinhoTotalObs, { childList: true, characterData: true, subtree: true });
    }

    // ── Habilitação do card de Pagamento conforme estado da venda ──
    const cardPagamento = document.getElementById('cardPagamento');
    const pagamentoAvisoInline = document.getElementById('pagamentoAvisoInline');
    const pagamentoAvisoTexto = document.getElementById('pagamentoAvisoTexto');

    function obterEstadoVenda() {
        const tipo = tipoVendaInput.value;
        if (tipo === 'produto') {
            if (carrinhoItens.length === 0) {
                return { pronto: false, motivo: 'Adicione ao menos 1 produto ao carrinho.' };
            }
            return { pronto: true, motivo: '' };
        }
        if (!servicoSelecionado) {
            return { pronto: false, motivo: 'Selecione um serviço para a venda.' };
        }
        const tipoServ = (typeof servicoSelecionado.tipo === 'object' && servicoSelecionado.tipo !== null)
            ? (servicoSelecionado.tipo.value || servicoSelecionado.tipo)
            : servicoSelecionado.tipo;
        if (tipoServ === 'pacote') {
            const vt = parseFloat(document.getElementById('valorTotal').value) || 0;
            if (vt <= 0) return { pronto: false, motivo: 'Informe o valor total do pacote.' };
        }
        return { pronto: true, motivo: '' };
    }

    const PAGAMENTO_DEFAULTS = {
        condicao: 'a_vista',
        forma: '',
        numeroParcelas: '2',
        primeiroVencimento: @json(now()->addDays(30)->format('Y-m-d')),
        mesReferencia: @json(now()->format('Y-m')),
    };

    function resetarPagamentoDefault() {
        condicaoSelect.value = PAGAMENTO_DEFAULTS.condicao;
        formaPagamentoSelect.value = PAGAMENTO_DEFAULTS.forma;
        numeroParcelasInput.value = PAGAMENTO_DEFAULTS.numeroParcelas;
        primeiroVencimento.value = PAGAMENTO_DEFAULTS.primeiroVencimento;
        mesReferencia.value = PAGAMENTO_DEFAULTS.mesReferencia;
        valorPorParcelaHint.textContent = '';
        previewCarneBadge.textContent = '';
        previewCarneInfo.textContent = 'Simulação das parcelas que serão geradas ao salvar.';
        carneTbody.innerHTML = '';
        carneTotalFoot.textContent = 'R$ 0,00';
    }

    let ultimoEstadoPronto = null;

    function atualizarHabilitacaoPagamento() {
        const { pronto, motivo } = obterEstadoVenda();

        // Reset dos campos só na transição pronto → não pronto, pra não apagar old() após erro de validação
        if (ultimoEstadoPronto === true && !pronto) {
            resetarPagamentoDefault();
        }
        ultimoEstadoPronto = pronto;

        cardPagamento.classList.toggle('opacity-50', !pronto);
        cardPagamento.querySelectorAll('input, select, textarea').forEach(function(el) {
            el.disabled = !pronto;
        });

        if (pronto) {
            pagamentoAvisoInline.style.display = 'none';
            aplicarCondicaoPagamento();
        } else {
            pagamentoAvisoTexto.textContent = motivo;
            pagamentoAvisoInline.style.display = '';
            previewCarneCard.style.display = 'none';
        }
    }

    // Recalcular habilitação quando o carrinho é alterado
    const origRenderCarrinho = renderCarrinho;
    renderCarrinho = function() {
        origRenderCarrinho.apply(this, arguments);
        atualizarHabilitacaoPagamento();
    };

    aplicarCondicaoPagamento();
    atualizarHabilitacaoPagamento();

    // Bloqueia submit se a venda não estiver pronta
    document.getElementById('formNovaVenda').addEventListener('submit', function(e) {
        const { pronto, motivo } = obterEstadoVenda();
        if (!pronto) {
            e.preventDefault();
            swalAlerta(motivo);
        }
    });

    // AJAX Search — Serviço
    initAjaxSearch({
        inputId: 'servicoSearch',
        hiddenId: 'servicoHidden',
        url: '{{ route("servicos.buscar") }}',
        renderItem: function(item) {
            var tipo = item.tipo;
            if (typeof tipo === 'object' && tipo !== null) tipo = tipo.value || tipo;
            var badge = tipo === 'pacote' ? '<span class="badge bg-info ms-1">Pacote</span>' : '<span class="badge bg-secondary ms-1">Avulso</span>';
            return '<strong>' + item.nome + '</strong> ' + badge + '<br><small class="text-muted">R$ ' + parseFloat(item.valor).toFixed(2).replace('.', ',') + ' — ' + item.duracao + ' min</small>';
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) {
            servicoSelecionado = item;
            aplicarTipoServico(item);
            atualizarHabilitacaoPagamento();
        },
        onClear: function() {
            servicoSelecionado = null;
            aplicarTipoServico(null);
            atualizarHabilitacaoPagamento();
        },
    });

    // AJAX Search — Cliente
    initAjaxSearch({
        inputId: 'clienteSearch',
        hiddenId: 'clienteHidden',
        url: '{{ route("clientes.buscar") }}',
        renderItem: function(item) {
            return '<strong>' + item.nome + '</strong>' + (item.telefone ? '<br><small class="text-muted">' + item.telefone + '</small>' : '');
        },
        displayText: function(item) { return item.nome; },
    });

    // AJAX Search — Produto
    initAjaxSearch({
        inputId: 'produtoSearch',
        hiddenId: 'produtoHidden',
        url: '{{ route("produtos.buscar") }}',
        renderItem: function(item) {
            return '<strong>' + item.nome + '</strong><br><small class="text-muted">R$ ' + parseFloat(item.valor_venda).toFixed(2).replace('.', ',') + ' — ' + item.quantidade + ' em estoque</small>';
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) {
            produtoSelecionado = item;
            produtoPreco.value = parseFloat(item.valor_venda).toFixed(2);
            produtoQtd.value = 1;
            produtoQtd.focus();
        },
        onClear: function() {
            produtoSelecionado = null;
            produtoPreco.value = '';
        },
    });
});
</script>
@endpush
