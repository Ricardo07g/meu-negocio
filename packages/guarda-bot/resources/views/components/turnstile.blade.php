{{--
    Widget do Cloudflare Turnstile.

    Nao renderiza nada quando as chaves nao estao configuradas: em dev e no CI a
    tela fica exatamente como antes, sem placeholder nem erro.

    CSP: a origem https://challenges.cloudflare.com precisa estar liberada em
    script-src e frame-src.
--}}
@if (\Ricardo07g\GuardaBot\Turnstile::estaAtivo())
    <div class="mb-3 d-flex justify-content-center">
        <div class="cf-turnstile"
             data-sitekey="{{ config('guarda-bot.turnstile.site_key') }}"
             data-language="{{ config('guarda-bot.turnstile.idioma', 'pt-br') }}"
             data-theme="auto"></div>
    </div>

    {{-- isset: o componente pode renderizar fora do grupo `web` (sem $errors). --}}
    @if (isset($errors) && $errors->has(\Ricardo07g\GuardaBot\Turnstile::CAMPO))
        <div class="text-danger small text-center mb-3">{{ $errors->first(\Ricardo07g\GuardaBot\Turnstile::CAMPO) }}</div>
    @endif

    @once
        {{-- defer: o widget nao bloqueia a renderizacao do formulario.

             Sem `integrity`: o api.js do Turnstile e versionless e atualizado pela
             Cloudflare, que nao publica hash. Fixar SRI derrubaria o widget na
             proxima atualizacao deles. A protecao real e a verificacao do token no
             servidor, que nao depende deste script. --}}
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
