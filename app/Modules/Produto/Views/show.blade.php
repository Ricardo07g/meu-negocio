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
                        {{-- Código --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-hash"></i>Código
                            </span>
                            <span>{{ $produto->codigo ?? '-' }}</span>
                        </li>
                        {{-- Código de Barras --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-bar-chart-2"></i>Código de Barras
                            </span>
                            <span>{{ $produto->codigo_barras ?? '-' }}</span>
                        </li>
                        {{-- Categoria --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-folder"></i>Categoria
                            </span>
                            <span>{{ $produto->categoriaProduto->nome ?? '-' }}</span>
                        </li>
                        {{-- Unidade --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-tag"></i>Unidade
                            </span>
                            <span>{{ $produto->unidade ?? '-' }}</span>
                        </li>
                        {{-- Preço de Venda --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-dollar-sign"></i>Preço de Venda
                            </span>
                            <span class="fw-bold">R$ {{ number_format($produto->valor_venda, 2, ',', '.') }}</span>
                        </li>
                        {{-- Preço de Custo --}}
                        <li class="hstack justify-content-between mb-4">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-dollar-sign"></i>Preço de Custo
                            </span>
                            <span>{{ $produto->valor_custo ? 'R$ ' . number_format($produto->valor_custo, 2, ',', '.') : '-' }}</span>
                        </li>
                        {{-- Margem --}}
                        <li class="hstack justify-content-between mb-4">
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
                        {{-- Quantidade em Estoque --}}
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
                        {{-- Estoque Mínimo --}}
                        <li class="hstack justify-content-between {{ $produto->descricao || $produto->observacoes ? 'mb-4' : 'mb-0' }}">
                            <span class="text-muted fw-medium hstack gap-3">
                                <i class="feather-alert-circle"></i>Estoque Mínimo
                            </span>
                            <span>{{ $produto->estoque_minimo ?? '-' }}</span>
                        </li>
                        {{-- Descrição --}}
                        @if($produto->descricao)
                        <li class="{{ $produto->observacoes ? 'mb-4' : 'mb-0' }}">
                            <span class="text-muted fw-medium hstack gap-3 mb-2">
                                <i class="feather-file-text"></i>Descrição
                            </span>
                            <span class="fs-13 d-block">{{ $produto->descricao }}</span>
                        </li>
                        @endif
                        {{-- Observações --}}
                        @if($produto->observacoes)
                        <li class="mb-0">
                            <span class="text-muted fw-medium hstack gap-3 mb-2">
                                <i class="feather-message-square"></i>Observações
                            </span>
                            <span class="fs-13 d-block">{{ $produto->observacoes }}</span>
                        </li>
                        @endif
                    </ul>

                    <div class="d-flex gap-2 text-center pt-4">
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
                <div class="card-header">
                    <h5 class="card-title">Movimentações de Estoque</h5>
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
                                @forelse($movimentos as $movimento)
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
                @if($movimentos->hasPages())
                    <div class="card-footer">
                        {{ $movimentos->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
