@extends('layouts.app')

@section('titulo', 'Nova Venda - Meu Negócio')
@section('titulo-pagina', 'Nova Venda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
    {{-- novalidate: a validacao do cliente e feita 100% via JS (coletarPendencias),
         evitando bloqueios nativos silenciosos (ex.: stepMismatch em valores). --}}
    <form action="{{ route('vendas.store') }}" method="POST" id="formNovaVenda" novalidate>
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

                {{-- Card com os dados do cliente selecionado (preenchido via JS) --}}
                <div id="clienteCard" class="mb-4" style="display:none;"></div>

                {{-- Empresa da venda (indicador quando 1 empresa; seletor quando várias) --}}
                <div class="row mb-4">
                    @include('partials.sub-seletor-empresa', [
                        'valorAtual' => $empresaId,
                        'modo' => 'editar',
                        'colunaCss' => 'col-md-6',
                        'rotulo' => 'Empresa da venda',
                    ])
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
                        <div class="bg-light border rounded p-3 mb-4">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fs-12 text-muted mb-1">Data de início <span class="text-danger">*</span></label>
                                    <input type="date" id="dataInicio" class="form-control" value="{{ old('data_inicio', now()->format('Y-m-d')) }}" disabled>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label fs-12 text-muted mb-1">Horário <span class="text-danger">*</span></label>
                                    <input type="time" name="horario" id="horario" class="form-control @error('horario') is-invalid @enderror" value="{{ old('horario', '09:00') }}" disabled>
                                    @error('horario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label fs-12 text-muted mb-1">Qtd. de etapas <span class="text-danger">*</span></label>
                                    <input type="number" id="qtdEtapasInput" class="form-control" value="{{ old('qtd_etapas') }}" min="2" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs-12 text-muted mb-1">Dias da semana <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                        @php $diasNomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; @endphp
                                        @foreach($diasNomes as $i => $dia)
                                        <input type="checkbox" class="btn-check dias-semana-check" value="{{ $i }}"
                                            id="dia{{ $i }}" autocomplete="off" {{ in_array($i, old('dias_semana', [])) ? 'checked' : '' }} disabled>
                                        <label class="btn btn-sm btn-outline-primary rounded-pill px-3" for="dia{{ $i }}">{{ $dia }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label fs-12 text-muted mb-1">Valor total (R$) <span class="text-danger">*</span></label>
                                    <input type="number" name="valor_total" id="valorTotal" class="form-control @error('valor_total') is-invalid @enderror" step="0.50" min="0.01" value="{{ old('valor_total') }}" disabled>
                                    @error('valor_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text"><span id="valorPorSessao"></span></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="btnGerarPreview" class="btn btn-primary">
                                    <i class="feather-calendar me-1"></i> Gerar preview das etapas
                                </button>
                            </div>
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

        {{-- Card Produtos da venda (visível quando tipo=produto) --}}
        <div class="card stretch stretch-full mt-4" id="card-carrinho" style="{{ old('tipo_venda', 'servico') === 'servico' ? 'display:none;' : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Produtos da venda</h5>
                <span id="carrinhoContador" class="badge bg-soft-primary text-primary d-none"></span>
            </div>
            <div class="card-body">
                {{-- Barra de inclusão: Produto + Qtd + Preço + Botão --}}
                <div class="bg-light border rounded p-3 mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-12 text-muted mb-1">Produto</label>
                            <div>
                                <input type="text" id="produtoSearch" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" disabled>
                                <input type="hidden" id="produtoHidden" value="">
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">Quantidade</label>
                            <input type="number" id="produtoQtd" class="form-control" value="1" min="1" disabled>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">Preço venda</label>
                            <input type="number" id="produtoPreco" class="form-control" step="0.50" min="0" placeholder="0,00" disabled>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="button" id="btnAdicionarProduto" class="btn btn-primary w-100" disabled>
                                <i class="feather-plus me-1"></i> Incluir item
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Estado vazio (sem itens) --}}
                <div id="carrinhoVazioBlock" class="text-center text-muted py-5">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-soft-primary text-primary mb-3" style="width:64px;height:64px;">
                        <i class="feather-shopping-cart" style="font-size:26px;"></i>
                    </span>
                    <div class="fw-semibold text-dark">Nenhum item adicionado</div>
                    <div class="fs-13">Busque um produto acima para começar.</div>
                </div>

                {{-- Lista de itens — DESKTOP (tabela) --}}
                <div class="d-none" id="carrinhoTabelaWrap">
                    <table class="table table-hover align-middle mb-0" id="tabelaCarrinho">
                        <thead>
                            <tr class="text-muted fs-12 text-uppercase">
                                <th style="width:38%;">Produto</th>
                                <th class="text-center" style="width:10%;">Qtd</th>
                                <th class="text-end" style="width:13%;">Vl. unit.</th>
                                <th class="text-end" style="width:12%;">Desconto</th>
                                <th class="text-end" style="width:12%;">Acréscimo</th>
                                <th class="text-end" style="width:12%;">Total</th>
                                <th class="text-center" style="width:5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="carrinhoTbody"></tbody>
                    </table>
                </div>

                {{-- Lista de itens — MOBILE (cards) --}}
                <div class="d-none" id="carrinhoCards"></div>

                {{-- Resumo financeiro --}}
                <div class="row mt-4 d-none" id="carrinhoResumo">
                    <div class="col-12 col-md-6 offset-md-6 col-lg-5 offset-lg-7 col-xl-4 offset-xl-8">
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-medium" id="carrinhoSubtotal">R$ 0,00</span>
                            </div>
                            <div class="justify-content-between mb-2 d-none" id="carrinhoDescontosRow" style="display:flex;">
                                <span class="text-muted">Descontos</span>
                                <span class="text-danger" id="carrinhoDescontos">R$ 0,00</span>
                            </div>
                            <div class="justify-content-between mb-2 d-none" id="carrinhoAcrescimosRow" style="display:flex;">
                                <span class="text-muted">Acréscimos</span>
                                <span id="carrinhoAcrescimos">R$ 0,00</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-dark">Total</span>
                                <span class="fw-bold fs-4" id="carrinhoTotal" style="color:var(--cor-destaque);">R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden inputs montados pelo JS antes do submit --}}
        <div id="carrinhoHiddenInputs"></div>

        {{-- Preview das etapas — logo após a configuração do serviço, antes do pagamento --}}
        <div class="card stretch stretch-full mt-4" id="previewCard" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Preview das etapas</h5>
                <span id="qtdEtapasBadge" class="badge bg-soft-primary text-primary"></span>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13 mb-3">
                    <i class="feather-edit-2 me-1"></i>
                    Ajuste a data e o horário de cada sessão antes de salvar. O dia da semana e o término recalculam automaticamente.
                </p>

                {{-- Aviso de sessões repetidas (mostrado via JS ao detectar duplicatas) --}}
                <div class="alert alert-danger d-flex align-items-center d-none" id="avisoEtapasDuplicadas">
                    <i class="feather-alert-triangle me-2"></i>
                    <span>Há sessões com a mesma data e horário (destacadas em vermelho). Ajuste-as para concluir a venda.</span>
                </div>

                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tabelaSessoes">
                        <thead class="position-sticky top-0 bg-white" style="z-index:1;">
                            <tr class="text-muted fs-12 text-uppercase">
                                <th style="width:8%;">#</th>
                                <th style="width:22%;">Dia</th>
                                <th style="width:30%;">Data</th>
                                <th style="width:22%;">Início</th>
                                <th style="width:18%;">Fim</th>
                            </tr>
                        </thead>
                        <tbody id="sessoesTbody"></tbody>
                    </table>
                </div>

                {{-- Resumo (Total + valor por etapa) --}}
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top" id="resumoEtapas">
                    <span class="text-muted fs-13" data-cel="resumo-etapa"></span>
                    <span class="fw-semibold text-dark">Total
                        <span class="fw-bold fs-4 ms-1" data-cel="resumo-total" style="color:var(--cor-destaque);">R$ 0,00</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Recebimentos --}}
        <div class="card stretch stretch-full mt-4" id="cardPagamento">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recebimentos</h5>
                <span id="pagamentoAvisoInline" class="badge bg-soft-warning text-warning" style="display:none;">
                    <i class="feather-alert-circle me-1"></i><span id="pagamentoAvisoTexto"></span>
                </span>
            </div>
            <div class="card-body">
                {{-- Painel fixo no topo (sempre visível): resumo (competência + totais) + barra de inclusão --}}
                <div class="bg-light border rounded p-3 mb-4" id="recebPainel">
                    {{-- Resumo — sempre visível ao olho do usuário --}}
                    <div class="row g-3 align-items-end" id="recebResumo">
                        <div class="col-12 col-md-3">
                            <label class="form-label fs-12 text-muted mb-1" for="mesReferencia">Competência <span class="text-danger">*</span></label>
                            <input type="month" name="mes_referencia" id="mesReferencia"
                                   class="form-control @error('mes_referencia') is-invalid @enderror"
                                   value="{{ old('mes_referencia', now()->format('Y-m')) }}" required>
                            @error('mes_referencia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-4 col-md-3">
                            <div class="fs-11 text-uppercase text-muted mb-1">Total da venda</div>
                            <div class="fw-semibold" id="recebTotalVenda">R$ 0,00</div>
                        </div>
                        <div class="col-4 col-md-3">
                            <div class="fs-11 text-uppercase text-muted mb-1">Recebido</div>
                            <div class="fw-semibold" id="recebTotalInformado">R$ 0,00</div>
                        </div>
                        <div class="col-4 col-md-3">
                            <div class="fs-11 text-uppercase text-muted mb-1">Falta receber</div>
                            <div class="fw-bold text-muted" id="recebDiferenca">R$ 0,00</div>
                        </div>
                    </div>

                    {{-- Barra de inclusão (some no crediário): forma + valor + parcelas + adicionar --}}
                    <div id="recebToolbar">
                        <hr class="my-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-5">
                                <label class="form-label fs-12 text-muted mb-1" for="recebFormaBusca">Forma de recebimento</label>
                                <div class="position-relative">
                                    <input type="text" id="recebFormaBusca" class="form-control" placeholder="Buscar forma de pagamento..." autocomplete="off">
                                    <div id="recebDropdown" class="border rounded bg-white shadow-sm py-1" style="display:none;position:absolute;z-index:1050;left:0;right:0;top:100%;max-height:220px;overflow:auto;"></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label fs-12 text-muted mb-1" for="recebValorNovo">Valor (R$)</label>
                                <input type="number" step="0.01" min="0.01" id="recebValorNovo" class="form-control" placeholder="0,00">
                            </div>
                            {{-- Parcelas no cartão: só quando a forma escolhida é de parcelamento --}}
                            <div class="col-6 col-md-2" id="recebParcelasNovoWrap" style="display:none;">
                                <label class="form-label fs-12 text-muted mb-1" for="recebParcelasNovo">
                                    Parcelas
                                    <x-label-info content="Em quantas vezes o cliente parcelou no cartão. Define a agenda dos recebíveis. O cliente já está quitado — quem recebe parcelado é a loja." />
                                </label>
                                <input type="number" min="1" step="1" id="recebParcelasNovo" class="form-control" value="1">
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="button" id="btnAdicionarRecebimento" class="btn btn-primary w-100">
                                    <i class="feather-plus me-1"></i> Adicionar
                                </button>
                            </div>
                        </div>
                        <div id="recebToolbarAviso" class="text-danger fs-13 mt-2" style="display:none;"></div>
                    </div>

                    {{-- Total já coberto: barra fica inativa --}}
                    <div id="recebCompletoInfo" class="alert alert-success py-2 px-3 fs-13 mt-3 mb-0" style="display:none;">
                        <i class="feather-info me-1"></i>Total da venda já coberto pelos recebimentos. Exclua um item para adicionar ou trocar uma forma.
                    </div>
                </div>

                {{-- Estado vazio --}}
                <div id="recebVazio" class="text-center text-muted py-3">
                    <div class="fs-13"><i class="feather-info me-1"></i>Nenhum recebimento adicionado. Busque uma forma acima.</div>
                </div>

                {{-- Itens de recebimento (montados pelo JS) --}}
                <div id="recebimentosLista"></div>

                {{-- Campos do crediário (a prazo): visíveis só quando a forma escolhida é crediário --}}
                <div class="row g-3 mt-1" id="crediarioCampos" style="display:none;">
                    <div class="col-12 col-sm-6 col-md-4" id="parcelasWrapper">
                        <label class="form-label" for="numeroParcelas">Número de Parcelas <span class="text-danger">*</span></label>
                        <input type="number" min="2" max="24" step="1" name="numero_parcelas" id="numeroParcelas"
                               class="form-control @error('numero_parcelas') is-invalid @enderror"
                               value="{{ old('numero_parcelas', 2) }}">
                        <div class="form-text" id="valorPorParcelaHint"></div>
                        @error('numero_parcelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-sm-6 col-md-4" id="primeiroVencimentoWrapper">
                        <label class="form-label" for="primeiroVencimento">Primeiro Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="primeiro_vencimento" id="primeiroVencimento"
                               class="form-control @error('primeiro_vencimento') is-invalid @enderror"
                               value="{{ old('primeiro_vencimento', now()->addDays(30)->format('Y-m-d')) }}"
                               min="{{ now()->format('Y-m-d') }}">
                        @error('primeiro_vencimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-sm-6 col-md-4" id="formaRecebimentoPrazoWrapper">
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

                    <div class="col-12" id="parceladoAviso">
                        <small class="text-muted">
                            <i class="feather-info me-1"></i>
                            A venda entra em <strong>Contas a Receber</strong> com as parcelas listadas abaixo.
                            Cada parcela é recebida e baixada individualmente.
                        </small>
                    </div>
                </div>

                @error('recebimentos')
                    <div class="alert alert-danger mt-3 mb-0 fs-13"><i class="feather-alert-triangle me-1"></i>{{ $message }}</div>
                @enderror
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

@php
    $formasJs = $formas->map(fn ($f) => [
        'id' => $f->id,
        'empresa_id' => $f->empresa_id,
        'nome' => $f->nome,
        'gera_recebivel' => (bool) $f->gera_recebivel,
        'permite_parcelas' => (bool) $f->permite_parcelas,
        'max_parcelas' => $f->max_parcelas,
        'forca_a_prazo' => $f->tipo->forcaAPrazo(),
    ])->values();
@endphp

@push('js')
<script>
window.vendaCreateConfig = {
    itensOld: @json($itensOld ?? []),
    clienteSelecionado: @json($clienteSelecionado ?? null),
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
    formas: @json($formasJs),
    empresaId: @json($empresaId),
    recebimentosOld: @json(array_values(old('recebimentos', []))),
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
