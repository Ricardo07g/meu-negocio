{{--
    Aba Seguranca do Meu Perfil: verificacao em duas etapas (ADR-0022).
    Tres estados: ativa, em configuracao ($qrCode presente) e desligada.
--}}
@php
    $redeExige = (bool) $usuario->rede?->exigir_2fa;
    $codigosNovos = session('codigos_recuperacao');
@endphp

<h5 class="fw-bold mb-1">Verificação em duas etapas</h5>
<p class="fs-12 text-muted mb-4">
    Além da senha, o login pede um código de 6 dígitos gerado por um aplicativo autenticador no seu celular
    (Google Authenticator, Microsoft Authenticator, Authy...). Mesmo que alguém descubra sua senha, não entra sem o celular.
</p>

@if($usuario->temDoisFatoresAtivo())
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="feather-check-circle"></i>
        <span>Ativa desde {{ $usuario->two_factor_confirmed_at->format('d/m/Y') }}.</span>
    </div>

    @if(is_array($codigosNovos))
        <div class="alert alert-warning mb-4" role="alert">
            <p class="fw-bold mb-2">Guarde seus códigos de recuperação</p>
            <p class="fs-12 mb-3">
                Cada código entra <strong>uma única vez</strong> se você perder o celular. Eles não serão mostrados de novo:
                anote ou imprima e guarde num lugar seguro.
            </p>
            <div class="row g-2 font-monospace" data-codigos-recuperacao>
                @foreach($codigosNovos as $codigo)
                    <div class="col-6 col-md-3"><span class="badge bg-light text-dark fs-13 w-100 py-2">{{ $codigo }}</span></div>
                @endforeach
            </div>
        </div>
    @endif

    <p class="fs-13 mb-4">
        Códigos de recuperação disponíveis: <strong>{{ count($usuario->two_factor_recovery_codes ?? []) }}</strong>
    </p>

    <div class="row g-4">
        <div class="col-md-6">
            {{-- Cada form le o proprio error bag (SenhaDoisFatoresRequest): o erro aparece onde a senha foi digitada. --}}
            <form action="{{ route('perfil.dois-fatores.codigos') }}" method="POST" class="border rounded p-3 h-100" data-form-2fa="codigos">
                @csrf
                <p class="fw-semibold mb-1">Gerar novos códigos</p>
                <p class="fs-12 text-muted">Os códigos atuais deixam de valer.</p>
                <input type="password" name="senha_2fa" class="form-control mb-3 @error('senha_2fa', 'codigos') is-invalid @enderror"
                       placeholder="Sua senha" autocomplete="current-password" required>
                @error('senha_2fa', 'codigos') <div class="invalid-feedback mb-3">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-light w-100"><i class="feather-refresh-cw me-2"></i>Gerar novos códigos</button>
            </form>
        </div>
        <div class="col-md-6">
            @if($redeExige)
                <div class="border rounded p-3 h-100">
                    <p class="fw-semibold mb-1">Desativar</p>
                    <p class="fs-12 text-muted mb-0">Sua rede exige a verificação em duas etapas, então ela não pode ser desativada.</p>
                </div>
            @else
                <form action="{{ route('perfil.dois-fatores.desativar') }}" method="POST" class="border rounded p-3 h-100" data-form-2fa="desativar"
                      data-confirm="Desativar a verificação em duas etapas? Sua conta volta a depender só da senha.">
                    @csrf @method('DELETE')
                    <p class="fw-semibold mb-1">Desativar</p>
                    <p class="fs-12 text-muted">O login volta a pedir só a senha.</p>
                    <input type="password" name="senha_2fa" class="form-control mb-3 @error('senha_2fa', 'desativar') is-invalid @enderror"
                           placeholder="Sua senha" autocomplete="current-password" required>
                    @error('senha_2fa', 'desativar') <div class="invalid-feedback mb-3">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn-outline-danger w-100"><i class="feather-shield-off me-2"></i>Desativar</button>
                </form>
            @endif
        </div>
    </div>
@elseif($qrCode)
    <ol class="fs-13 ps-3 mb-4">
        <li class="mb-2">Instale um aplicativo autenticador no celular, se ainda não tiver.</li>
        <li class="mb-2">No aplicativo, adicione uma conta e escaneie o QR code abaixo.</li>
        <li>Digite o código de 6 dígitos que aparecer para concluir.</li>
    </ol>

    <div class="row align-items-center g-4 mb-4">
        <div class="col-md-auto text-center">
            {{-- SVG gerado no servidor pelo bacon-qr-code a partir do segredo do proprio usuario. --}}
            <div class="d-inline-block border rounded p-2 bg-white" data-qr-dois-fatores>{!! $qrCode !!}</div>
        </div>
        <div class="col-md">
            <p class="fs-12 text-muted mb-1">Não consegue escanear? Digite esta chave no aplicativo:</p>
            <code class="fs-13 d-block text-break">{{ trim(chunk_split((string) $usuario->two_factor_secret, 4, ' ')) }}</code>
        </div>
    </div>

    <form action="{{ route('perfil.dois-fatores.confirmar') }}" method="POST" data-form-2fa="confirmar">
        @csrf
        <div class="row g-3 align-items-start">
            <div class="col-md-6">
                <input type="text" name="codigo" class="form-control text-center fs-16 @error('codigo') is-invalid @enderror"
                       placeholder="000000" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required>
                @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary w-100"><i class="feather-check me-2"></i>Confirmar e ativar</button>
            </div>
        </div>
    </form>
@else
    @if($redeExige)
        <div class="alert alert-warning mb-4" role="alert">
            Sua rede exige a verificação em duas etapas. Configure-a para continuar usando o sistema.
        </div>
    @endif

    <form action="{{ route('perfil.dois-fatores.iniciar') }}" method="POST" data-form-2fa="iniciar">
        @csrf
        <div class="row g-3 align-items-start">
            <div class="col-md-6">
                <input type="password" name="senha_2fa" class="form-control @error('senha_2fa', 'iniciar') is-invalid @enderror"
                       placeholder="Confirme sua senha" autocomplete="current-password" required>
                @error('senha_2fa', 'iniciar') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary w-100"><i class="feather-shield me-2"></i>Configurar verificação em duas etapas</button>
            </div>
        </div>
    </form>
@endif
