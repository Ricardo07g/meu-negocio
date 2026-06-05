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

                    {{-- Campos Servico Unico --}}
                    <div id="campos-unico" style="display:none;">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Data <span class="text-danger">*</span></label>
                                <input type="date" name="data" id="dataUnico" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', now()->format('Y-m-d')) }}" disabled>
                                @error('data') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Horário <span class="text-danger">*</span></label>
                                <input type="time" name="horario" id="horarioUnico" class="form-control @error('horario') is-invalid @enderror" value="{{ old('horario', '09:00') }}" disabled>
                                @error('horario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end pb-2">
                                <span id="fimCalculadoUnico" class="text-muted fs-13"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Campos Servico em Etapas --}}
                    <div id="campos-etapas" style="display:none;">
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
                                <label class="form-label">Qtd Etapas <span class="text-danger">*</span></label>
                                <input type="number" id="qtdEtapasInput" class="form-control" value="{{ old('qtd_etapas') }}" min="2" disabled>
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
                                <i class="feather-calendar me-1"></i> Gerar Preview das Etapas
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

        {{-- Preview das etapas — acima do preview das parcelas --}}
        <div class="card stretch stretch-full mt-4" id="previewCard" style="display:none;">
            <div class="card-header">
                <h5 class="card-title">Preview das Etapas <span id="qtdEtapasBadge" class="badge bg-primary ms-2"></span></h5>
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
window.vendaCreateConfig = {
    itensOld: @json($itensOld ?? []),
    servicoOld: @if($servicoOld ?? false) {
        id: {{ $servicoOld->id }},
        nome: @json($servicoOld->nome),
        tipo: @json($servicoOld->tipo->value),
        valor: {{ $servicoOld->valor }},
        duracao: {{ $servicoOld->duracao }},
        qtd_etapas: {{ $servicoOld->qtd_etapas ?? 'null' }},
    } @else null @endif,
    oldDatas: @json(old('datas', [])),
    oldHorarios: @json(old('horarios', [])),
    pagamentoDefaults: {
        primeiroVencimento: @json(now()->addDays(30)->format('Y-m-d')),
        mesReferencia: @json(now()->format('Y-m')),
    },
    urls: {
        servicos: '{{ route('servicos.buscar') }}',
        clientes: '{{ route('clientes.buscar') }}',
        produtos: '{{ route('produtos.buscar') }}',
    },
};
</script>
@vite('resources/js/venda-create.js')
@endpush
