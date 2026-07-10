@extends('layouts.app')

@section('titulo', 'Clientes - Meu Negocio')
@section('titulo-pagina', 'Clientes')
@section('breadcrumb')
    <li class="breadcrumb-item active">Clientes</li>
@endsection

@section('content')
    {{-- Botao Novo Cliente --}}
    @can('cliente.criar')
    <div class="row mb-4">
        <div class="col-xxl-3 col-md-6">
            <a href="{{ route('clientes.create') }}" class="btn btn-primary w-100">
                <i class="feather-plus me-2"></i>Novo Cliente
            </a>
        </div>
    </div>
    @endcan

    {{-- Filtros --}}
    <div class="card stretch stretch-full mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('clientes.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nome, telefone, email, CPF ou cidade..." value="{{ request('q') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Situação financeira
                            <x-label-info content="<b>Em dia</b>: cliente não tem contas em aberto ou só tem parcelas com vencimento futuro.<br><b>Pendente</b>: tem parcelas em aberto, mas nenhuma vencida.<br><b>Vencido</b>: tem ao menos uma parcela com vencimento no passado sem ter sido paga." />
                        </label>
                        <select name="situacao_financeira" class="form-select">
                            <option value="">Todas</option>
                            <option value="em_dia" @selected(request('situacao_financeira') === 'em_dia')>Em dia</option>
                            <option value="pendente" @selected(request('situacao_financeira') === 'pendente')>Pendente</option>
                            <option value="vencido" @selected(request('situacao_financeira') === 'vencido')>Vencido</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            Atividade
                            <x-label-info content="Classifica o cliente pela data do último atendimento ou venda.<br><b>Ativo</b>: teve movimento nos últimos 30 dias.<br><b>Sumido 30+/60+/90+/180+ dias</b>: último contato foi há mais tempo — útil para campanhas de reengajamento.<br><b>Novo</b>: cadastrado nos últimos 30 dias." />
                        </label>
                        <select name="atividade" class="form-select">
                            <option value="">Todas</option>
                            <option value="ativo" @selected(request('atividade') === 'ativo')>Ativo (últimos 30 dias)</option>
                            <option value="sumido_30" @selected(request('atividade') === 'sumido_30')>Sumido 30+ dias</option>
                            <option value="sumido_60" @selected(request('atividade') === 'sumido_60')>Sumido 60+ dias</option>
                            <option value="sumido_90" @selected(request('atividade') === 'sumido_90')>Sumido 90+ dias</option>
                            <option value="sumido_180" @selected(request('atividade') === 'sumido_180')>Sumido 180+ dias</option>
                            <option value="novo" @selected(request('atividade') === 'novo')>Novo (últimos 30 dias)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Extras</label>
                        <div class="d-flex gap-4 pt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="aniversariantes" value="1" id="fAniversariantes" @checked(request('aniversariantes'))>
                                <label class="form-check-label" for="fAniversariantes">
                                    <i class="feather-gift me-1"></i>Aniversariantes do mês
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="com_whatsapp" value="1" id="fWhatsapp" @checked(request('com_whatsapp'))>
                                <label class="form-check-label" for="fWhatsapp">
                                    <i class="feather-message-circle me-1"></i>Com WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('clientes.index') }}" class="btn btn-light" title="Limpar filtros">
                            <i class="feather-x me-1"></i>Limpar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:56px"></th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Cidade</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                        <tr>
                            <td><x-thumb :url="$cliente->imagem_thumb_url" :nome="$cliente->nome" /></td>
                            <td>{{ $cliente->nome }}</td>
                            <td>
                                {{ $cliente->telefone ?? '-' }}
                                @if($cliente->telefone_whatsapp)
                                    <i class="feather-message-circle text-success ms-1" title="WhatsApp"></i>
                                @endif
                            </td>
                            <td>{{ $cliente->email ?? '-' }}</td>
                            <td>{{ $cliente->cidade ? $cliente->cidade . ($cliente->estado ? '/' . $cliente->estado : '') : '-' }}</td>
                            <td>
                                <div class="hstack gap-2 justify-content-end">
                                    <div class="dropdown">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('clientes.show', $cliente) }}">
                                                    <i class="feather-eye me-3"></i>
                                                    <span>Ver</span>
                                                </a>
                                            </li>
                                            @can('cliente.editar')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('clientes.edit', $cliente) }}">
                                                    <i class="feather-edit-3 me-3"></i>
                                                    <span>Editar</span>
                                                </a>
                                            </li>
                                            @endcan
                                            @can('cliente.excluir')
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" data-confirm="Excluir este cliente?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-3"></i>
                                                        <span>Excluir</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum cliente cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($clientes->hasPages())
            <div class="card-footer">
                {{ $clientes->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
