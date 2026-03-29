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
        .nxl-container .nxl-content .main-content { overflow-x: visible; }
        .modal-backdrop ~ .nxl-container,
        body.modal-open .nxl-container { filter: none !important; -webkit-filter: none !important; }

        /* Sidebar */
        .nxl-navigation { background: #1b2e4b !important; }
        .nxl-navigation .navbar-wrapper { background: #1b2e4b !important; }
        .nxl-navigation .m-header { background: #162640 !important; }
        .nxl-navigation .m-header .b-brand { color: #fff !important; }
        .nxl-navigation .nxl-link { color: #c8d6e5 !important; }
        .nxl-navigation .nxl-micon i { color: #94a3b8 !important; }
        .nxl-navigation .nxl-item .nxl-link:hover,
        .nxl-navigation .nxl-item.active > .nxl-link { background: #243b55 !important; color: #fff !important; }
        .nxl-navigation .nxl-item .nxl-link:hover .nxl-micon i,
        .nxl-navigation .nxl-item.active > .nxl-link .nxl-micon i { color: #fff !important; }
        .nxl-navigation .nxl-item .nxl-link:hover .nxl-mtext,
        .nxl-navigation .nxl-item.active > .nxl-link .nxl-mtext { color: #fff !important; }
        .nxl-navigation .nxl-caption label { color: #64748b !important; text-transform: uppercase; font-size: 11px; }
        .nxl-navigation .navbar-content { border-color: #243b55 !important; }

        /* Header */
        .nxl-header { background: #1b2e4b !important; border-bottom: 1px solid #243b55 !important; }
        .nxl-header .header-wrapper { background: #1b2e4b !important; }
        .nxl-header .page-header-title h5 { color: #fff !important; }
        .nxl-header .breadcrumb-item a { color: #c8d6e5 !important; }
        .nxl-header .breadcrumb-item.active,
        .nxl-header .breadcrumb-item + .breadcrumb-item::before { color: #7f8fa6 !important; }

        /* Header - hamburger menu (3 barras) */
        .nxl-header .hamburger-inner,
        .nxl-header .hamburger-inner::before,
        .nxl-header .hamburger-inner::after { background-color: #fff !important; }

        /* Header - ícones esquerda (toggle, mega menu) */
        .nxl-header .nxl-navigation-toggle a,
        .nxl-header .nxl-navigation-toggle a i,
        .nxl-header .nxl-lavel-mega-menu-toggle a,
        .nxl-header .nxl-lavel-mega-menu-toggle a i,
        .nxl-header .nxl-head-mobile-toggler { color: #fff !important; }

        /* Header - ícones direita (dark mode, avatar) */
        .nxl-header .dark-button,
        .nxl-header .dark-button i,
        .nxl-header .nxl-head-link,
        .nxl-header .nxl-head-link i { color: #fff !important; }
        .nxl-header .nxl-head-link:hover,
        .nxl-header .dark-button:hover { background: #243b55 !important; border-radius: 8px; }

        /* Header - avatar círculo */
        .nxl-header .avtar-s,
        .nxl-header .avatar-text { background: #3b82f6 !important; color: #fff !important; }
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
                    <li class="nxl-item nxl-caption">
                        <label>Menu Principal</label>
                    </li>
                    {{-- Dashboard --}}
                    <li class="nxl-item">
                        <a href="{{ route('dashboard') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboard</span>
                        </a>
                    </li>
                    {{-- Agenda --}}
                    @can('agendamento.ver')
                    <li class="nxl-item">
                        <a href="{{ route('agenda.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-calendar"></i></span>
                            <span class="nxl-mtext">Agenda</span>
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
                    {{-- Clientes --}}
                    @can('cliente.ver')
                    <li class="nxl-item">
                        <a href="{{ route('clientes.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Clientes</span>
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
                    {{-- Produtos --}}
                    @can('produto.ver')
                    <li class="nxl-item">
                        <a href="{{ route('produtos.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-package"></i></span>
                            <span class="nxl-mtext">Produtos</span>
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
                    {{-- Papeis --}}
                    @can('papel.ver')
                    <li class="nxl-item">
                        <a href="{{ route('papeis.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Papéis</span>
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
                        <h5 class="m-b-10">@yield('titulo-pagina', 'Dashboard')</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
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
                        if (result.isConfirmed) {
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
    @stack('js')
</body>

</html>
