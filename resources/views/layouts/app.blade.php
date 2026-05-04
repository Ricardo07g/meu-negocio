<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Meu Negócio')</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/sweetalert2.min.css') }}">
    @stack('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
    <style>
        /* Variáveis de cores (tema claro original - altere aqui para customizar) */
        :root {
            --cor-fundo: #ffffff;
            --cor-fundo-escuro: #f8f9fa;
            --cor-fundo-hover: #e9ecef;
            --cor-texto: #283c50;
            --cor-texto-claro: #6c757d;
            --cor-icone: #94a3b8;
            --cor-texto-muted: #6c757d;
            --cor-texto-sutil: #adb5bd;
            --cor-destaque: #3454d1;
        }

        .nxl-container .nxl-content .main-content { overflow-x: visible; }
        .modal-backdrop ~ .nxl-container,
        body.modal-open .nxl-container { filter: none !important; -webkit-filter: none !important; }

        /* Sidebar minimenu: empurra conteúdo ao expandir no hover */
        html.minimenu .nxl-container,
        html.minimenu .nxl-header,
        html.minimenu .page-header { transition: all .3s ease; }
        html.minimenu:has(.nxl-navigation:hover) .nxl-container { margin-left: 280px !important; }
        html.minimenu:has(.nxl-navigation:hover) .nxl-header { left: 280px !important; }
        html.minimenu:has(.nxl-navigation:hover) .page-header { left: 280px !important; }
        html.minimenu-hover .nxl-container { margin-left: 280px !important; }
        html.minimenu-hover .nxl-header { left: 280px !important; }
        html.minimenu-hover .page-header { left: 280px !important; }
        html.minimenu .nxl-navigation .navbar-content,
        html.minimenu .nxl-navigation .navbar-wrapper,
        html.minimenu .nxl-navigation .m-header { transition: width .3s ease !important; }
        html.minimenu .nxl-navigation:hover .m-header,
        html.minimenu .nxl-navigation:hover .navbar-wrapper { width: 280px !important; }

        /* Botões - cor primária customizável via variável */
        .btn-primary,
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active { background-color: var(--cor-destaque) !important; border-color: var(--cor-destaque) !important; }

        /* Alinha altura de botoes com form-control/form-select do Duralux (~50px: padding 24 + line-height 24 + border 2) */
        .btn-group .btn,
        .btn.w-100,
        .btn.flex-fill,
        .d-flex.gap-2 > .btn { min-height: calc(3rem + 2px); }

        /* Alinha btn-sm no carrinho com form-control-sm (~47px: padding 24 + 14*1.5 + 2) */
        #tabelaCarrinho .btn-sm { min-height: calc(2.8125rem + 2px); }

        /* ── Ícone de ajuda nos filtros (x-label-info) ───────────── */
        .label-info-icon {
            font-size: 16px;
            color: #8a94a6;
            cursor: help;
            vertical-align: -2px;
            transition: color .15s ease;
        }
        .label-info-icon:hover { color: #3454d1; }

        /* ── Tooltip customizado para explicações de filtros ─────── */
        .tooltip.tooltip-label-info { --bs-tooltip-max-width: 320px; }
        .tooltip.tooltip-label-info .tooltip-inner {
            max-width: 320px;
            padding: 10px 14px;
            font-size: 13px;
            line-height: 1.55;
            text-align: left;
            color: #f8f9fb;
            background-color: #1f2a3d;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .18);
            letter-spacing: .1px;
        }
        .tooltip.tooltip-label-info .tooltip-inner b,
        .tooltip.tooltip-label-info .tooltip-inner strong {
            color: #8ab4ff;
            font-weight: 600;
        }
        .tooltip.tooltip-label-info .tooltip-inner br + b,
        .tooltip.tooltip-label-info .tooltip-inner br + strong { display: inline-block; margin-top: 4px; }
        .tooltip.tooltip-label-info .tooltip-arrow::before { border-top-color: #1f2a3d; }
        .tooltip.tooltip-label-info.bs-tooltip-bottom .tooltip-arrow::before { border-bottom-color: #1f2a3d; }
        .tooltip.tooltip-label-info.bs-tooltip-start .tooltip-arrow::before { border-left-color: #1f2a3d; }
        .tooltip.tooltip-label-info.bs-tooltip-end .tooltip-arrow::before { border-right-color: #1f2a3d; }
    </style>
</head>

<body>
    {{-- Sidebar Navigation --}}
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="{{ route('dashboard') }}" class="b-brand">
                    <img src="{{ asset('assets/images/logo-full.png') }}" alt="Meu Negócio" class="logo logo-lg">
                    <img src="{{ asset('assets/images/logo-abbr.png') }}" alt="Meu Negócio" class="logo logo-sm">
                </a>
            </div>
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    {{-- Dashboard --}}
                    <li class="nxl-item">
                        <a href="{{ route('dashboard') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Início</span>
                        </a>
                    </li>

                    <li class="nxl-item nxl-caption">
                        <label>Cadastros</label>
                    </li>
                    {{-- Clientes --}}
                    @can('cliente.ver')
                    <li class="nxl-item">
                        <a href="{{ route('clientes.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Clientes</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Produtos --}}
                    @can('produto.ver')
                    <li class="nxl-item">
                        <a href="{{ route('produtos.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-package"></i></span>
                            <span class="nxl-mtext">Produtos</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Servicos --}}
                    @can('servico.ver')
                    <li class="nxl-item">
                        <a href="{{ route('servicos.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                            <span class="nxl-mtext">Serviços</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Vendas --}}
                    @can('agendamento.ver')
                    <li class="nxl-item">
                        <a href="{{ route('vendas.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shopping-bag"></i></span>
                            <span class="nxl-mtext">Vendas</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Agendamentos --}}
                    <li class="nxl-item nxl-caption">
                        <label>Agendamentos</label>
                    </li>
                    @can('agendamento.ver')
                    <li class="nxl-item">
                        <a href="{{ route('agenda.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-calendar"></i></span>
                            <span class="nxl-mtext">Agenda</span>
                        </a>
                    </li>
                    @endcan

                    @if(auth()->user()->rede->plano->tem_financeiro)
                    <li class="nxl-item nxl-caption">
                        <label>Financeiro</label>
                    </li>
                    {{-- Pagamentos --}}
                    @can('pagamento.ver')
                    <li class="nxl-item">
                        <a href="{{ route('pagamentos.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-dollar-sign"></i></span>
                            <span class="nxl-mtext">Contas a Receber</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Despesas --}}
                    @can('despesa.ver')
                    <li class="nxl-item">
                        <a href="{{ route('despesas.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-trending-down"></i></span>
                            <span class="nxl-mtext">Contas a Pagar</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Caixa --}}
                    @can('financeiro.ver')
                    <li class="nxl-item">
                        <a href="{{ route('caixas.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-inbox"></i></span>
                            <span class="nxl-mtext">Caixa</span>
                        </a>
                    </li>
                    @endcan
                    @endif

                    @if(auth()->user()->rede->plano->tem_estoque)
                    <li class="nxl-item nxl-caption">
                        <label>Estoque</label>
                    </li>
                    {{-- Movimentações de Estoque --}}
                    @can('movimento_estoque.ver')
                    <li class="nxl-item">
                        <a href="{{ route('movimentos-estoque.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-repeat"></i></span>
                            <span class="nxl-mtext">Movimentações</span>
                        </a>
                    </li>
                    @endcan
                    @endif

                    {{-- Cadastros auxiliares (submenu) --}}
                    @if(auth()->user()->can('produto.ver') || auth()->user()->can('categoria_despesa.ver'))
                    @php
                        $rotaAtiva = request()->routeIs('categorias-produto.*') || request()->routeIs('categorias-despesa.*');
                    @endphp
                    <li class="nxl-item nxl-hasmenu {{ $rotaAtiva ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-folder"></i></span>
                            <span class="nxl-mtext">Cadastros</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu" @if($rotaAtiva) style="display:block;" @endif>
                            @can('produto.ver')
                            <li class="nxl-item {{ request()->routeIs('categorias-produto.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('categorias-produto.index') }}">Categorias de Produto</a>
                            </li>
                            @endcan
                            @can('categoria_despesa.ver')
                            <li class="nxl-item {{ request()->routeIs('categorias-despesa.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('categorias-despesa.index') }}">Categorias de Despesa</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    <li class="nxl-item nxl-caption">
                        <label>Administração</label>
                    </li>
                    {{-- Empresas --}}
                    @can('empresa.ver')
                    <li class="nxl-item">
                        <a href="{{ route('empresas.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-home"></i></span>
                            <span class="nxl-mtext">Empresas</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Usuarios --}}
                    @can('usuario.ver')
                    <li class="nxl-item">
                        <a href="{{ route('usuarios.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-user"></i></span>
                            <span class="nxl-mtext">Usuários</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Perfis de Acesso --}}
                    @can('papel.ver')
                    <li class="nxl-item">
                        <a href="{{ route('perfis-acesso.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Perfis de Acesso</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
        </div>
    </nav>

    {{-- Header --}}
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expander-button" style="display: none;">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
                <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                    <a href="javascript:void(0);" id="nxl-lavel-mega-menu-open">
                        <i class="feather-align-left"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="nxl-h-item dark-lavel-toggle">
                        <a href="javascript:void(0);" class="nxl-head-link dark-button">
                            <i class="feather-moon"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <div class="avatar-text avatar-md bg-primary text-white">
                                {{ mb_substr(auth()->user()->nome, 0, 1) }}
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-text avatar-md bg-primary text-white me-3">
                                        {{ mb_substr(auth()->user()->nome, 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="text-dark mb-0">{{ auth()->user()->nome }}</h6>
                                        <span class="fs-12 fw-medium text-muted">{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('perfil.index') }}" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Meu Perfil</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="feather-log-out"></i>
                                    <span>Sair</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">@yield('titulo-pagina', 'Início')</h5>
                    </div>
                    <ul class="breadcrumb">
                        @yield('breadcrumb')
                    </ul>
                </div>
            </div>
            <div class="main-content">
                {{-- Flash messages --}}
                @if(session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if(session('erro'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('erro') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>

    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/sweetalert2.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar minimenu: empurra conteúdo ao hover
            document.addEventListener('mouseover', function(e) {
                if (!document.documentElement.classList.contains('minimenu')) return;
                var nav = e.target.closest('.nxl-navigation');
                if (nav) {
                    document.documentElement.classList.add('minimenu-hover');
                } else {
                    document.documentElement.classList.remove('minimenu-hover');
                }
            });

            // Dropdown fix: ao abrir, libera overflow do table-responsive
            document.querySelectorAll('.table-responsive').forEach(function(container) {
                container.addEventListener('show.bs.dropdown', function() {
                    container.style.overflow = 'visible';
                });
                container.addEventListener('hidden.bs.dropdown', function() {
                    container.style.overflow = 'auto';
                });
            });

            // SweetAlert2: confirmação em forms com data-confirm
            document.querySelectorAll('form[data-confirm]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (form.dataset.confirmed === 'true') {
                        form.removeAttribute('data-confirmed');
                        return;
                    }
                    e.preventDefault();
                    Swal.fire({
                        title: 'Tem certeza?',
                        text: form.dataset.confirm,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, confirmar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result.value) {
                            form.dataset.confirmed = 'true';
                            form.requestSubmit();
                        }
                    });
                });
            });

            // SweetAlert2: alertas nos forms de venda (substituir alert())
            window.swalAlerta = function(msg) {
                Swal.fire({ icon: 'warning', title: 'Atenção', text: msg, confirmButtonColor: '#3085d6' });
            };

            // Renegociacao de parcela — delegacao de evento + diagnostico no console.
            console.info('[renegociar] handler v3 ativo (delegacao no body)');
            document.body.addEventListener('click', function (e) {
                var link = e.target.closest('.js-renegociar-parcela');
                if (! link) return;
                e.preventDefault();
                e.stopPropagation();

                console.info('[renegociar] click capturado', link);

                var action = link.dataset.action;
                var valor = link.dataset.valor;
                var vencimento = link.dataset.vencimento;
                var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                if (! action) {
                    console.error('[renegociar] data-action ausente', link);
                    return;
                }
                if (! csrf) {
                    console.error('[renegociar] CSRF token nao encontrado no meta');
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Token CSRF ausente. Recarregue a pagina.' });
                    return;
                }
                if (typeof Swal === 'undefined') {
                    console.error('[renegociar] SweetAlert2 (Swal) nao carregado');
                    return;
                }

                Swal.fire({
                    title: 'Renegociar parcela',
                    html:
                        '<div class="text-start">' +
                        '<label class="form-label mb-1">Novo vencimento</label>' +
                        '<input id="swalVenc" type="date" class="form-control mb-3" value="' + vencimento + '" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                        '<label class="form-label mb-1">Novo valor (R$)</label>' +
                        '<input id="swalValor" type="number" step="0.01" min="0.01" class="form-control mb-3" value="' + parseFloat(valor).toFixed(2) + '" style="width:100%;max-width:100%;box-sizing:border-box;">' +
                        '<label class="form-label mb-1">Motivo / observação</label>' +
                        '<textarea id="swalObs" rows="3" class="form-control" style="width:100%;max-width:100%;box-sizing:border-box;"></textarea>' +
                        '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Salvar renegociação',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3454d1',
                    focusConfirm: false,
                    preConfirm: function () {
                        var v = document.getElementById('swalVenc').value;
                        var vl = document.getElementById('swalValor').value;
                        if (!v || !vl) {
                            Swal.showValidationMessage('Preencha vencimento e valor.');
                            return false;
                        }
                        return { vencimento: v, valor: vl, obs: document.getElementById('swalObs').value };
                    }
                }).then(function (res) {
                    console.info('[renegociar] swal resultado', res);
                    // Compatibilidade com SweetAlert2 antigo (sem `isConfirmed`):
                    // o que importa e ter `value` (preenchido pelo preConfirm).
                    if (! res || ! res.value) return;

                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = action;
                    form.style.display = 'none';
                    form.acceptCharset = 'UTF-8';

                    function input(name, value) {
                        var i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = name;
                        i.value = value;
                        return i;
                    }
                    form.appendChild(input('_token', csrf));
                    form.appendChild(input('_method', 'PATCH'));
                    form.appendChild(input('data_vencimento', res.value.vencimento));
                    form.appendChild(input('valor', res.value.valor));
                    form.appendChild(input('observacao', res.value.obs || ''));

                    document.body.appendChild(form);
                    console.info('[renegociar] submetendo form', { action: action });
                    form.submit();
                }).catch(function (err) {
                    console.error('[renegociar] erro no Swal.fire().then()', err);
                });
            });
        });
    </script>

    {{-- SweetAlert2: flash messages --}}
    @if(session('sucesso'))
    <script>
        Swal.fire({ icon: 'success', title: 'Sucesso!', text: '{{ session('sucesso') }}', timer: 3000, showConfirmButton: false });
    </script>
    @endif
    @if(session('erro'))
    <script>
        Swal.fire({ icon: 'error', title: 'Erro', text: '{{ session('erro') }}' });
    </script>
    @endif
    {{-- Máscaras e CEP auto-fill --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function aplicarMascara(input, mascara) {
            input.addEventListener('input', function() {
                var v = this.value.replace(/\D/g, '');
                var r = '';
                var i = 0;
                for (var m = 0; m < mascara.length && i < v.length; m++) {
                    if (mascara[m] === '0') {
                        r += v[i]; i++;
                    } else {
                        r += mascara[m];
                    }
                }
                this.value = r;
            });
        }

        document.querySelectorAll('.mask-telefone').forEach(function(el) { aplicarMascara(el, '(00) 00000-0000'); });
        document.querySelectorAll('.mask-cpf').forEach(function(el) { aplicarMascara(el, '000.000.000-00'); });
        document.querySelectorAll('.mask-cep').forEach(function(el) { aplicarMascara(el, '00000-000'); });
        document.querySelectorAll('.mask-data').forEach(function(el) { aplicarMascara(el, '00/00/0000'); });

        // CEP auto-fill via ViaCEP
        document.querySelectorAll('.mask-cep').forEach(function(el) {
            el.addEventListener('blur', function() {
                var cep = this.value.replace(/\D/g, '');
                if (cep.length !== 8) return;
                fetch('https://viacep.com.br/ws/' + cep + '/json/')
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.erro) return;
                        var f = function(id, val) { var el = document.getElementById(id); if (el && val) el.value = val; };
                        f('logradouro', d.logradouro);
                        f('bairro', d.bairro);
                        f('cidade', d.localidade);
                        var estado = document.getElementById('estado');
                        if (estado && d.uf) { estado.value = d.uf; }
                    })
                    .catch(function() {});
            });
        });
    });
    </script>
    <script>
    function initAjaxSearch(config) {
        var input = document.getElementById(config.inputId);
        var hidden = document.getElementById(config.hiddenId);
        if (!input) return;

        var dropdown = document.createElement('div');
        dropdown.className = 'ajax-search-dropdown';
        dropdown.style.cssText = 'display:none;position:absolute;z-index:1050;background:#fff;border:1px solid #dee2e6;border-top:0;border-radius:0 0 6px 6px;max-height:250px;overflow-y:auto;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dropdown);

        var timer = null;

        input.addEventListener('input', function() {
            var q = this.value.trim();
            if (hidden) hidden.value = '';
            if (config.onClear) config.onClear();
            clearTimeout(timer);
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch(config.url + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!items.length) {
                        dropdown.innerHTML = '<div style="padding:10px 14px;color:#6c757d;">Nenhum resultado</div>';
                        dropdown.style.display = 'block';
                        return;
                    }
                    dropdown.innerHTML = '';
                    items.forEach(function(item) {
                        var div = document.createElement('div');
                        div.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid #f0f0f0;';
                        div.innerHTML = config.renderItem(item);
                        div.addEventListener('mouseenter', function() { this.style.background = '#f8f9fa'; });
                        div.addEventListener('mouseleave', function() { this.style.background = '#fff'; });
                        div.addEventListener('click', function() {
                            input.value = config.displayText(item);
                            if (hidden) hidden.value = item.id;
                            dropdown.style.display = 'none';
                            if (config.onSelect) config.onSelect(item);
                        });
                        dropdown.appendChild(div);
                    });
                    dropdown.style.display = 'block';
                });
            }, 300);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') dropdown.style.display = 'none';
        });

        document.addEventListener('click', function(e) {
            if (!input.parentElement.contains(e.target)) dropdown.style.display = 'none';
        });
    }
    </script>

    <script>
    // Atalho que expande accordion + seleciona aba especifica em cards (venda/pagamento/despesa)
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-open-payments]');
        if (!trigger) return;
        e.preventDefault();

        const collapseEl = document.querySelector(trigger.dataset.openPayments);
        const tabEl = document.querySelector(`[data-bs-target="${trigger.dataset.targetTab}"]`);
        if (!collapseEl || !tabEl) return;

        bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
        bootstrap.Tab.getOrCreateInstance(tabEl).show();

        collapseEl.addEventListener('shown.bs.collapse', function handler() {
            collapseEl.removeEventListener('shown.bs.collapse', handler);
            collapseEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    </script>

    @stack('js')
</body>

</html>
