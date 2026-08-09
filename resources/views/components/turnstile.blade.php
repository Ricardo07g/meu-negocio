{{--
    Widget do Cloudflare Turnstile.

    Nao renderiza nada quando as chaves nao estao configuradas — em dev e no CI a
    tela fica exatamente como antes, sem placeholder nem erro.
--}}
@if (\App\Support\Turnstile::estaAtivo())
    <div class="mb-3 d-flex justify-content-center">
        <div class="cf-turnstile"
             data-sitekey="{{ config('services.turnstile.site_key') }}"
             data-language="pt-br"
             data-theme="auto"></div>
    </div>

    @error(\App\Support\Turnstile::CAMPO)
        <div class="text-danger small text-center mb-3">{{ $message }}</div>
    @enderror

    @once
        {{-- defer: o widget nao bloqueia a renderizacao do formulario.

             Sem `integrity`: o api.js do Turnstile e versionless e atualizado pela
             Cloudflare, e eles nao publicam hash — fixar SRI derrubaria o widget na
             proxima atualizacao deles. A protecao aqui e a verificacao server-side
             do token (App\Support\Turnstile), que nao depende deste script. --}}
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
