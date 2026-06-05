<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Meu Negócio')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
</head>

<body>
    <a href="{{ route('home') }}" class="position-absolute top-0 start-0 m-4 d-inline-flex align-items-center gap-2 text-muted fs-13 fw-medium text-decoration-none" style="z-index:10;">
        <i class="feather-arrow-left"></i> Voltar ao site
    </a>
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-4 mx-sm-0 position-relative">
                    <a href="{{ route('home') }}" class="position-absolute translate-middle top-0 start-50 shadow-lg d-inline-block" style="border-radius:14px;line-height:0;" title="Voltar ao site" aria-label="Voltar para a página inicial">
                        @include('partials.logo-mark', ['size' => 52])
                    </a>
                    <div class="card-body p-sm-5">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
</body>

</html>
