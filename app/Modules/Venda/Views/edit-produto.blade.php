@extends('layouts.app')

@section('titulo', 'Editar Venda de Produto - Meu Negócio')
@section('titulo-pagina', 'Editar Venda de Produto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('vendas.index') }}">Vendas</a></li>
    <li class="breadcrumb-item active">Editar venda</li>
@endsection

@section('content')
    <form action="{{ route('vendas.update-produto', $vendaProduto) }}" method="POST" id="formEditarVendaProduto">
        @csrf @method('PATCH')

        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Venda #{{ $vendaProduto->id }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <input type="text" id="clienteSearch" class="form-control @error('cliente_id') is-invalid @enderror" placeholder="Digite o nome ou telefone..." autocomplete="off" value="{{ old('_cliente_nome', $vendaProduto->cliente->nome ?? '') }}">
                        <input type="hidden" name="cliente_id" id="clienteHidden" value="{{ old('cliente_id', $vendaProduto->cliente_id) }}">
                        @error('cliente_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adicionar produto</label>
                        <input type="text" id="produtoSearch" class="form-control" placeholder="Busque por nome do produto..." autocomplete="off">
                        <input type="hidden" id="produtoHidden">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabelaItens">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="text-center" style="width:110px;">Qtd</th>
                                <th class="text-end" style="width:140px;">Unit. (R$)</th>
                                <th class="text-end" style="width:120px;">Desc. (R$)</th>
                                <th class="text-end" style="width:120px;">Acr. (R$)</th>
                                <th class="text-end" style="width:130px;">Subtotal</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itensBody">
                            @foreach($vendaProduto->itens as $i => $item)
                                <tr data-row>
                                    <td>
                                        <input type="hidden" name="itens[{{ $i }}][id]" value="{{ $item->id }}">
                                        <input type="hidden" name="itens[{{ $i }}][produto_id]" value="{{ $item->produto_id }}">
                                        {{ $item->descricao }}
                                    </td>
                                    <td><input type="number" min="1" class="form-control form-control-sm text-center" name="itens[{{ $i }}][quantidade]" value="{{ $item->quantidade }}" data-qtd></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[{{ $i }}][valor_unitario]" value="{{ number_format((float) $item->valor_unitario, 2, '.', '') }}" data-unit></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[{{ $i }}][desconto]" value="{{ number_format((float) $item->desconto, 2, '.', '') }}" data-desc></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[{{ $i }}][acrescimo]" value="{{ number_format((float) $item->acrescimo, 2, '.', '') }}" data-acr></td>
                                    <td class="text-end fw-semibold" data-subtotal>R$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-light text-danger" data-remover>
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('itens') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Desconto global (R$)</label>
                        <input type="number" step="0.01" min="0" name="desconto" id="descontoGlobal" class="form-control @error('desconto') is-invalid @enderror" value="{{ old('desconto', number_format((float) $vendaProduto->desconto, 2, '.', '')) }}">
                        @error('desconto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Acréscimo global (R$)</label>
                        <input type="number" step="0.01" min="0" name="acrescimo" id="acrescimoGlobal" class="form-control @error('acrescimo') is-invalid @enderror" value="{{ old('acrescimo', number_format((float) $vendaProduto->acrescimo, 2, '.', '')) }}">
                        @error('acrescimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total</label>
                        <input type="text" class="form-control fw-bold" id="totalCalc" value="R$ {{ number_format((float) $vendaProduto->valor_total, 2, ',', '.') }}" disabled>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observações</label>
                        <textarea name="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao', $vendaProduto->observacao) }}</textarea>
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

    let proximoIndex = {{ $vendaProduto->itens->count() }};
    const tabelaBody = document.getElementById('itensBody');

    initAjaxSearch({
        inputId: 'produtoSearch',
        hiddenId: 'produtoHidden',
        url: '{{ route("produtos.buscar") }}',
        renderItem: function(item) {
            return '<strong>' + item.nome + '</strong><br><small class="text-muted">R$ ' + parseFloat(item.valor_venda).toFixed(2).replace('.', ',') + ' — ' + item.quantidade + ' em estoque</small>';
        },
        displayText: function(item) { return item.nome; },
        onSelect: function(item) {
            adicionarItem(item);
            document.getElementById('produtoSearch').value = '';
            document.getElementById('produtoHidden').value = '';
        },
    });

    function adicionarItem(produto) {
        const i = proximoIndex++;
        const valor = parseFloat(produto.valor_venda).toFixed(2);
        const tr = document.createElement('tr');
        tr.setAttribute('data-row', '');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="itens[${i}][produto_id]" value="${produto.id}">
                ${produto.nome}
            </td>
            <td><input type="number" min="1" class="form-control form-control-sm text-center" name="itens[${i}][quantidade]" value="1" data-qtd></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[${i}][valor_unitario]" value="${valor}" data-unit></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[${i}][desconto]" value="0.00" data-desc></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="itens[${i}][acrescimo]" value="0.00" data-acr></td>
            <td class="text-end fw-semibold" data-subtotal>R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-light text-danger" data-remover>
                    <i class="feather-trash-2"></i>
                </button>
            </td>
        `;
        tabelaBody.appendChild(tr);
        recalcularTudo();
    }

    function calcularLinha(tr) {
        const q = parseFloat(tr.querySelector('[data-qtd]').value || 0);
        const u = parseFloat(tr.querySelector('[data-unit]').value || 0);
        const d = parseFloat(tr.querySelector('[data-desc]').value || 0);
        const a = parseFloat(tr.querySelector('[data-acr]').value || 0);
        const sub = (q * u) - d + a;
        tr.querySelector('[data-subtotal]').textContent = 'R$ ' + sub.toFixed(2).replace('.', ',');
        return sub;
    }

    function recalcularTudo() {
        let subtotal = 0;
        tabelaBody.querySelectorAll('[data-row]').forEach(tr => {
            subtotal += calcularLinha(tr);
        });
        const d = parseFloat(document.getElementById('descontoGlobal').value || 0);
        const a = parseFloat(document.getElementById('acrescimoGlobal').value || 0);
        const total = subtotal - d + a;
        document.getElementById('totalCalc').value = 'R$ ' + total.toFixed(2).replace('.', ',');
    }

    document.addEventListener('input', function (e) {
        if (e.target.closest('#tabelaItens') || e.target.id === 'descontoGlobal' || e.target.id === 'acrescimoGlobal') {
            recalcularTudo();
        }
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-remover]');
        if (!btn) return;
        const rows = tabelaBody.querySelectorAll('[data-row]');
        if (rows.length <= 1) {
            alert('A venda precisa ter ao menos um item.');
            return;
        }
        btn.closest('[data-row]').remove();
        recalcularTudo();
    });

    document.getElementById('formEditarVendaProduto').addEventListener('submit', function (e) {
        if (tabelaBody.querySelectorAll('[data-row]').length === 0) {
            e.preventDefault();
            alert('A venda precisa ter ao menos um item.');
        }
    });
</script>
@endpush
