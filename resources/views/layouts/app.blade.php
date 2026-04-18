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
    @stack('js')
</body>

</html>
