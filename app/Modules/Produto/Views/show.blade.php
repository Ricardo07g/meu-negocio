@extends('layouts.app')

@section('titulo', 'Produto - Meu Negócio')
@section('titulo-pagina', $produto->nome)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('produtos.index') }}">Produtos</a></li>
    <li class="breadcrumb-item active">{{ $produto->nome }}</li>
@endsection

@section('content')
    <div class="row">
        {{-- Coluna esquerda: Dados do produto --}}
        <div class="col-xxl-4 col-xl-5">
            {{-- Identificação --}}
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <div class="wd-80 ht-80 mx-auto mb-3">
                            <div class="avatar-text avatar-xl bg-primary text-white rounded-circle fs-24 fw-bold">
                                <i class="feather-package"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-1">{{ $produto->nome }}</h5>
                        @if($produto->ativo)
                            <span class="badge bg-success">Ativo</span>
                        @else
                            <span class="badge bg-danger">Inativo</span>
                        @endif
                    </div>

                    <ul class="list-unstyled mb-4">
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-hash"></i>Código
                            </span>
                            <span>{{ $produto->codigo ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-bar-chart-2"></i>Código de Barras
                            </span>
                            <span>{{ $produto->codigo_barras ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-folder"></i>Categoria
                            </span>
                            <span>{{ $produto->categoriaProduto->nome ?? '-' }}</span>
                        </li>
                        @if($produto->descricao)
                        <li class="mb-0">
                            <span class="text-muted fw-medium hstack gap-3 mb-2">
                                <i class="feather-file-text"></i>Descrição
                            </span>
                            <span class="fs-13">{{ $produto->descricao }}</span>
                        </li>
                        @endif
                    </ul>

                    @if($produto->observacoes)
                    <div class="border-top pt-3">
                        <span class="text-muted fw-medium hstack gap-3 mb-2">
                            <i class="feather-message-square"></i>Observações
                        </span>
                        <p class="fs-13 mb-0">{{ $produto->observacoes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Preços --}}
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Preços</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-dollar-sign"></i>Preço de Venda
                            </span>
                            <span class="fw-bold fs-16">R$ {{ number_format($produto->valor_venda, 2, ',', '.') }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-dollar-sign"></i>Preço de Custo
                            </span>
                            <span>{{ $produto->valor_custo ? 'R$ ' . number_format($produto->valor_custo, 2, ',', '.') : '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-0">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-trending-up"></i>Margem
                            </span>
                            <span>
                                @if($produto->valor_custo && $produto->valor_custo > 0)
                                    @php
                                        $margem = (($produto->valor_venda - $produto->valor_custo) / $produto->valor_custo) * 100;
                                    @endphp
                                    <span class="{{ $margem >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                        {{ number_format($margem, 1, ',', '.') }}%
                                    </span>
                                @else
                                    -
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Estoque --}}
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Estoque</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-box"></i>Quantidade
                            </span>
                            <span>
                                @if($produto->estoque_minimo !== null && $produto->quantidade <= $produto->estoque_minimo)
                                    <span class="text-danger fw-bold">{{ $produto->quantidade }}</span>
                                    <i class="feather-alert-triangle text-danger ms-1" title="Estoque baixo"></i>
                                @else
                                    <span class="fw-bold">{{ $produto->quantidade }}</span>
                                @endif
                            </span>
                        </li>
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-alert-circle"></i>Estoque Mínimo
                            </span>
                            <span>{{ $produto->estoque_minimo ?? '-' }}</span>
                        </li>
                        <li class="hstack justify-content-between mb-0">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-tag"></i>Unidade
                            </span>
                            <span>{{ $produto->unidade ?? '-' }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Botões Voltar / Editar --}}
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex gap-2 text-center">
                        <a href="{{ route('produtos.index') }}" class="w-50 btn btn-light">
                            <i class="feather-arrow-left me-2"></i>
                            <span>Voltar</span>
                        </a>
                        @can('produto.editar')
                        <a href="{{ route('produtos.edit', $produto) }}" class="w-50 btn btn-primary">
                            <i class="feather-edit me-2"></i>
                            <span>Editar</span>
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        {{-- Coluna direita: Movimentações de Estoque --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title">Movimentações de Estoque</h5>
                    @can('movimento_estoque.criar')
                    <a href="{{ route('movimentos-estoque.create', ['produto_id' => $produto->id]) }}" class="btn btn-sm btn-primary">
                        <i class="feather-plus me-1"></i> Novo Movimento
                    </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($produto->movimentos ?? [] as $movimento)
                                <tr>
                                    <td>
                                        @switch($movimento->tipo->value)
                                            @case('entrada')
                                                <span class="badge bg-success">Entrada</span>
                                                @break
                                            @case('saida')
                                                <span class="badge bg-danger">Saída</span>
                                                @break
                                            @case('ajuste')
                                                <span class="badge bg-warning">Ajuste</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ ucfirst($movimento->tipo->value) }}</span>
                                        @endswitch
                                    </td>
                                    <td>{{ $movimento->quantidade }}</td>
                                    <td>{{ $movimento->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhum movimento registrado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
