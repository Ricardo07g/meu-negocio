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
        :root {
            --cor-fundo: #3d4f65;
            --cor-fundo-escuro: #344559;
            --cor-fundo-hover: #506380;
            --cor-texto: #fff;
            --cor-texto-claro: #c8d6e5;
            --cor-icone: #94a3b8;
            --cor-texto-muted: #64748b;
            --cor-texto-sutil: #7f8fa6;
            --cor-destaque: #3454d1;
        }

        .nxl-container .nxl-content .main-content { overflow-x: visible; }
        .modal-backdrop ~ .nxl-container,
        body.modal-open .nxl-container { filter: none !important; -webkit-filter: none !important; }

        /* Sidebar */
        .nxl-navigation { background: var(--cor-fundo) !important; }
        .nxl-navigation .navbar-wrapper { background: var(--cor-fundo) !important; }
        .nxl-navigation .m-header { background: var(--cor-fundo-escuro) !important; }
        .nxl-navigation .m-header .b-brand { color: var(--cor-texto) !important; }
        .nxl-navigation .nxl-link { color: var(--cor-texto-claro) !important; }
        .nxl-navigation .nxl-micon i { color: var(--cor-icone) !important; }
        .nxl-navigation .nxl-item .nxl-link:hover,
        .nxl-navigation .nxl-item.active > .nxl-link { background: var(--cor-fundo-hover) !important; color: var(--cor-texto) !important; }
        .nxl-navigation .nxl-item .nxl-link:hover .nxl-micon i,
        .nxl-navigation .nxl-item.active > .nxl-link .nxl-micon i { color: var(--cor-texto) !important; }
        .nxl-navigation .nxl-item .nxl-link:hover .nxl-mtext,
        .nxl-navigation .nxl-item.active > .nxl-link .nxl-mtext { color: var(--cor-texto) !important; }
        .nxl-navigation .nxl-caption label { color: var(--cor-texto-muted) !important; text-transform: uppercase; font-size: 11px; }
        .nxl-navigation .navbar-content { border-color: var(--cor-fundo-hover) !important; }

        /* Sidebar minimenu: empurra conteúdo ao expandir no hover */
        html.minimenu .nxl-container,
        html.minimenu .nxl-header,
        html.minimenu .page-header { transition: all .3s ease; }

        /* Abordagem 1: CSS :has() (navegadores modernos) */
        html.minimenu:has(.nxl-navigation:hover) .nxl-container { margin-left: 280px !important; }
        html.minimenu:has(.nxl-navigation:hover) .nxl-header { left: 280px !important; }
        html.minimenu:has(.nxl-navigation:hover) .page-header { left: 280px !important; }

        /* Abordagem 2: fallback via JS class (todos os navegadores) */
        html.minimenu-hover .nxl-container { margin-left: 280px !important; }
        html.minimenu-hover .nxl-header { left: 280px !important; }
        html.minimenu-hover .page-header { left: 280px !important; }

        /* Fundo do sidebar: força cor escura + transição só em width (evita flash branco) */
        html.minimenu .nxl-navigation .navbar-content {
            background-color: var(--cor-fundo) !important;
            transition: width .3s ease !important;
        }
        html.minimenu .nxl-navigation .navbar-wrapper {
            background: var(--cor-fundo) !important;
            transition: width .3s ease !important;
        }
        html.minimenu .nxl-navigation .m-header {
            background: var(--cor-fundo-escuro) !important;
            transition: width .3s ease !important;
        }
        html.minimenu .nxl-navigation:hover .m-header,
        html.minimenu .nxl-navigation:hover .navbar-wrapper { width: 280px !important; }

        /* Header */
        .nxl-header { background: var(--cor-fundo) !important; border-bottom: 1px solid var(--cor-fundo-hover) !important; }
        .nxl-header .header-wrapper { background: var(--cor-fundo) !important; }
        .nxl-header .page-header-title h5 { color: var(--cor-texto) !important; }
        .nxl-header .breadcrumb-item a { color: var(--cor-texto-claro) !important; }
        .nxl-header .breadcrumb-item.active,
        .nxl-header .breadcrumb-item + .breadcrumb-item::before { color: var(--cor-texto-sutil) !important; }

        /* Header - hamburger menu (3 barras) */
        .nxl-header .hamburger-inner,
        .nxl-header .hamburger-inner::before,
        .nxl-header .hamburger-inner::after { background-color: var(--cor-texto) !important; }

        /* Header - ícones esquerda (toggle, mega menu) */
        .nxl-header .nxl-navigation-toggle a,
        .nxl-header .nxl-navigation-toggle a i,
        .nxl-header .nxl-lavel-mega-menu-toggle a,
        .nxl-header .nxl-lavel-mega-menu-toggle a i,
        .nxl-header .nxl-head-mobile-toggler { color: var(--cor-texto) !important; }

        /* Header - ícones direita (dark mode, avatar) */
        .nxl-header .dark-button,
        .nxl-header .dark-button i,
        .nxl-header .nxl-head-link,
        .nxl-header .nxl-head-link i { color: var(--cor-texto) !important; }
        .nxl-header .nxl-head-link:hover,
        .nxl-header .dark-button:hover { background: var(--cor-fundo-hover) !important; border-radius: 8px; }

        /* Header - avatar círculo */
        .nxl-header .avtar-s,
        .nxl-header .avatar-text { background: var(--cor-destaque) !important; color: var(--cor-texto) !important; }

        /* Botões - sobrescreve cor primária do tema */
        .btn-primary,
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active { background-color: var(--cor-destaque) !important; border-color: var(--cor-destaque) !important; }
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
                    {{-- Categorias de Produto --}}
                    @can('produto.ver')
                    <li class="nxl-item">
                        <a href="{{ route('categorias-produto.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-grid"></i></span>
                            <span class="nxl-mtext">Categorias</span>
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
                            <span class="nxl-mtext">Pagamentos</span>
                        </a>
                    </li>
                    @endcan
                    {{-- Despesas --}}
                    @can('despesa.ver')
                    <li class="nxl-item">
                        <a href="{{ route('despesas.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-trending-down"></i></span>
                            <span class="nxl-mtext">Despesas</span>
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
                    {{-- Papeis (somente planos que permitem) --}}
                    @if(auth()->user()->rede->plano->permiteGerenciarPapeis())
                    @can('papel.ver')
                    <li class="nxl-item">
                        <a href="{{ route('papeis.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Papéis</span>
                        </a>
                    </li>
                    @endcan
                    @endif
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
                            {{-- TODO: Meu Perfil --}}
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
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
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
    @stack('js')
</body>

</html>
