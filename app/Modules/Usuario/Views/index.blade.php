@extends('layouts.app')

@section('titulo', 'Usuários - Meu Negócio')
@section('titulo-pagina', 'Usuários')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuários</li>
@endsection

@section('content')
    @include('partials.aviso-limite-plano', [
        'recurso' => 'usuarios',
        'atual' => $limite['atual'],
        'maximo' => $limite['maximo'],
        'atingido' => $limite['atingido'],
        'rotaCriar' => route('usuarios.create'),
        'labelBotao' => 'Novo Usuário',
        'permissaoBlade' => 'usuario.criar',
    ])

    {{-- Politica de 2FA da rede (ADR-0022) — so o Admin ve e altera --}}
    @php $redeAtual = auth()->user()->rede; @endphp
    @can('configurarSeguranca', $redeAtual)
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="fw-bold mb-1"><i class="feather-shield me-2"></i>Verificação em duas etapas</p>
                    <p class="fs-12 text-muted mb-0">
                        @if($redeAtual->exigir_2fa)
                            <span class="badge bg-soft-success text-success me-1">Obrigatória</span>
                            Todos os usuários precisam configurá-la para usar o sistema.
                        @else
                            <span class="badge bg-soft-secondary text-secondary me-1">Opcional</span>
                            Cada usuário decide se ativa no Meu Perfil.
                        @endif
                    </p>
                </div>
                <form action="{{ route('rede.exigir-2fa') }}" method="POST"
                      @unless($redeAtual->exigir_2fa) data-confirm="Exigir a verificação em duas etapas? Quem ainda não configurou será levado a configurar no próximo acesso." @endunless>
                    @csrf @method('PATCH')
                    <input type="hidden" name="exigir" value="{{ $redeAtual->exigir_2fa ? 0 : 1 }}">
                    <button type="submit" class="btn {{ $redeAtual->exigir_2fa ? 'btn-light' : 'btn-primary' }}">
                        {{ $redeAtual->exigir_2fa ? 'Tornar opcional' : 'Exigir de todos' }}
                    </button>
                </form>
            </div>
        </div>
    @endcan

    {{-- Card with table --}}
    <div class="card stretch stretch-full">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover tabela-empilha mb-0">
                    <thead>
                        <tr>
                            <th style="width:56px"></th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Perfil de Acesso</th>
                            <th>2FA</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usuarios as $usuario)
                        <tr>
                            <td><x-thumb :url="$usuario->imagem_thumb_url" :nome="$usuario->nome" /></td>
                            <td data-label="Nome">{{ $usuario->nome }}</td>
                            <td data-label="Email">{{ $usuario->email }}</td>
                            <td data-label="Status">
                                @if($usuario->ativo)
                                    <span class="badge bg-soft-success text-success">Ativo</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Inativo</span>
                                @endif
                            </td>
                            <td data-label="Perfil de Acesso">{{ $usuario->getRoleNames()->first() ?? '-' }}</td>
                            <td data-label="2FA">
                                @if($usuario->temDoisFatoresAtivo())
                                    <span class="badge bg-soft-success text-success">Ativo</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Desligado</span>
                                @endif
                            </td>
                            <td>
                                <x-acoes-linha :editar="route('usuarios.edit', $usuario)"
                                               permissaoEditar="usuario.editar"
                                               :excluir="route('usuarios.destroy', $usuario)"
                                               permissaoExcluir="usuario.excluir"
                                               confirmacao="Excluir este usuário?" />
                            </td>
                        </tr>
                        @empty
                        <tr class="sem-registros"><td colspan="7" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-paginacao :paginator="$usuarios" />
    </div>
@endsection
