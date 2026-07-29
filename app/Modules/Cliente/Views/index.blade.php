@extends('layouts.app')

@section('titulo', 'Clientes - Meu Negocio')
@section('titulo-pagina', 'Clientes')
@section('breadcrumb')
    <li class="breadcrumb-item active">Clientes</li>
@endsection

@section('content')
    {{-- Botao Novo Cliente --}}
    <x-botao-novo :rota="route('clientes.create')" label="Novo Cliente" permissao="cliente.criar" />

    {{-- Filtros --}}
    <x-filtros-listagem :action="route('clientes.index')">
        <div class="col-12">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" placeholder="Nome, telefone, email, CPF ou cidade..." value="{{ request('q') }}">
        </div>

        <div class="col-12 col-sm-6 col-md-4">
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
        <div class="col-12 col-sm-6 col-md-4">
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
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label d-block">Extras</label>
            <div class="d-flex flex-wrap gap-4 pt-2">
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
    </x-filtros-listagem>

    {{-- Tabela --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
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
                            <td data-label="Nome">{{ $cliente->nome }}</td>
                            <td data-label="Telefone">
                                {{ $cliente->telefone ?? '-' }}
                                @if($cliente->telefone_whatsapp)
                                    <i class="feather-message-circle text-success ms-1" title="WhatsApp"></i>
                                @endif
                            </td>
                            <td data-label="Email">{{ $cliente->email ?? '-' }}</td>
                            <td data-label="Cidade">{{ $cliente->cidade ? $cliente->cidade . ($cliente->estado ? '/' . $cliente->estado : '') : '-' }}</td>
                            <td>
                                <x-acoes-linha :ver="route('clientes.show', $cliente)"
                                               :editar="route('clientes.edit', $cliente)"
                                               :excluir="route('clientes.destroy', $cliente)"
                                               permissaoEditar="cliente.editar"
                                               permissaoExcluir="cliente.excluir"
                                               confirmacao="Excluir este cliente?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="6" class="text-center text-muted py-4">Nenhum cliente cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$clientes" />
    </div>
@endsection
