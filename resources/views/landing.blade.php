<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Negócio — Gestão completa para clínicas, salões e autônomos</title>
    <meta name="description" content="Agenda, vendas, financeiro, caixa e estoque integrados num só lugar. Sistema de gestão para clínicas, salões, massoterapia e profissionais autônomos.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>

<body class="antialiased">
    @php $github = 'https://github.com/Ricardo07g/meu-negocio'; @endphp

    {{-- ───────────────────────── Header ───────────────────────── --}}
    <header data-landing-header class="fixed inset-x-0 top-0 z-50 transition-all duration-300">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                @include('partials.logo-mark', ['size' => 34])
                <span class="font-display text-lg font-extrabold tracking-tight text-ink">Meu Negócio</span>
            </a>
            <div class="hidden items-center gap-8 md:flex">
                <a href="#recursos" class="text-sm font-medium text-ink-soft transition hover:text-ink">Recursos</a>
                <a href="#para-quem" class="text-sm font-medium text-ink-soft transition hover:text-ink">Para quem é</a>
                <a href="#como-funciona" class="text-sm font-medium text-ink-soft transition hover:text-ink">Como funciona</a>
                <a href="{{ $github }}" target="_blank" rel="noopener" class="text-sm font-medium text-ink-soft transition hover:text-ink">GitHub</a>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-ink transition hover:bg-brand-50 sm:inline-block">Entrar</a>
                <a href="{{ route('registrar') }}" class="rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-brand-600/30 transition hover:bg-brand-700">Criar conta</a>
                <button data-nav-toggle class="ml-1 rounded-lg p-2 text-ink transition hover:bg-brand-50 md:hidden" aria-label="Abrir menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                </button>
            </div>
        </nav>
        <div data-mobile-nav class="hidden border-t border-ink/5 bg-white px-5 py-4 md:hidden">
            <div class="flex flex-col gap-3">
                <a href="#recursos" class="text-sm font-medium text-ink-soft">Recursos</a>
                <a href="#para-quem" class="text-sm font-medium text-ink-soft">Para quem é</a>
                <a href="#como-funciona" class="text-sm font-medium text-ink-soft">Como funciona</a>
                <a href="{{ $github }}" target="_blank" rel="noopener" class="text-sm font-medium text-ink-soft">GitHub</a>
                <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-700">Entrar</a>
            </div>
        </div>
    </header>

    {{-- ───────────────────────── Hero ───────────────────────── --}}
    <section class="mn-hero-mesh relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
        <div class="pointer-events-none absolute inset-0 mn-grid-faint"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-2">
            <div>
                <span class="mn-rise inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 px-3 py-1 text-xs font-semibold text-brand-700 backdrop-blur" style="--d:0ms">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span> Gestão sem planilhas
                </span>
                <h1 class="mn-rise mt-5 font-display text-4xl font-extrabold leading-[1.04] tracking-tight text-ink sm:text-6xl" style="--d:80ms">
                    Tudo do seu negócio <span class="text-brand-600">em um só lugar</span>.
                </h1>
                <p class="mn-rise mt-5 max-w-md text-lg leading-relaxed text-ink-soft" style="--d:160ms">
                    Agenda, vendas, financeiro, caixa e estoque integrados — feito para clínicas, salões, massoterapia e profissionais autônomos.
                </p>
                <div class="mn-rise mt-8 flex flex-wrap items-center gap-3" style="--d:240ms">
                    <a href="{{ route('registrar') }}" class="group inline-flex items-center gap-2 rounded-full bg-brand-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700 hover:shadow-brand-600/40">
                        Começar agora
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform group-hover:translate-x-0.5"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-full border border-ink/15 bg-white px-6 py-3 text-base font-semibold text-ink transition hover:border-ink/30">
                        Já tenho conta
                    </a>
                </div>
                <div class="mn-rise mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium text-ink-soft" style="--d:320ms">
                    @foreach (['Multi-empresa', 'Multiusuário', 'Open source'] as $t)
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-accent"><path d="M20 6 9 17l-5-5" /></svg>
                            {{ $t }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Preview do painel (mock em HTML/CSS) --}}
            <div class="mn-rise relative" style="--d:200ms">
                <div class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-gradient-to-tr from-brand-500/25 via-brand-400/10 to-accent/20 blur-2xl"></div>
                <div class="rotate-[-1.5deg] rounded-2xl border border-ink/10 bg-white p-5 shadow-2xl shadow-brand-700/10">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @include('partials.logo-mark', ['size' => 22])
                            <span class="text-sm font-bold text-ink">Painel</span>
                        </div>
                        <div class="flex gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-ink/10"></span>
                            <span class="h-2 w-2 rounded-full bg-ink/10"></span>
                            <span class="h-2 w-2 rounded-full bg-accent"></span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-brand-50 p-3">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-brand-700/70">Receita</div>
                            <div class="mt-1 font-display text-lg font-extrabold text-ink">R$ 18,4k</div>
                        </div>
                        <div class="rounded-xl bg-accent-soft p-3">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-accent/80">Agenda hoje</div>
                            <div class="mt-1 font-display text-lg font-extrabold text-ink">12</div>
                        </div>
                        <div class="rounded-xl bg-ink/5 p-3">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-ink-soft">A receber</div>
                            <div class="mt-1 font-display text-lg font-extrabold text-ink">R$ 3,2k</div>
                        </div>
                    </div>
                    <div class="mt-4 rounded-xl bg-brand-50/60 p-4">
                        <div class="mb-3 text-xs font-semibold text-ink-soft">Fluxo financeiro · 6 meses</div>
                        <div class="flex h-24 items-end gap-2">
                            @foreach ([42, 58, 50, 72, 64, 88] as $i => $h)
                                <div class="flex-1 rounded-t-md {{ $i === 5 ? 'bg-brand-600' : 'bg-brand-300' }}" style="height: {{ $h }}%"></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ([['09:00', 'Maria S.', 'Limpeza de pele', 'bg-accent'], ['10:30', 'João P.', 'Massagem', 'bg-brand-500'], ['14:00', 'Ana L.', 'Corte + escova', 'bg-amber-400']] as $ag)
                            <div class="flex items-center gap-3 rounded-lg border border-ink/5 px-3 py-2">
                                <span class="h-8 w-1 rounded-full {{ $ag[3] }}"></span>
                                <span class="text-xs font-bold text-ink">{{ $ag[0] }}</span>
                                <span class="text-xs font-medium text-ink">{{ $ag[1] }}</span>
                                <span class="ml-auto text-xs text-ink-soft">{{ $ag[2] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Recursos ───────────────────────── --}}
    <section id="recursos" class="mx-auto max-w-6xl px-5 py-20 sm:py-28">
        <div class="mn-reveal mx-auto max-w-2xl text-center">
            <span class="text-sm font-bold uppercase tracking-widest text-brand-600">Recursos</span>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Do agendamento ao caixa, sem trocar de sistema</h2>
            <p class="mt-4 text-lg text-ink-soft">Os módulos conversam entre si: uma venda já agenda, lança o financeiro e baixa o estoque.</p>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $recursos = [
                    ['Agenda', 'Calendário por profissional e empresa, com confirmação, finalização e cancelamento.', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
                    ['Vendas', 'Serviço avulso, serviço em etapas ou produtos — à vista ou a prazo, com carrinho.', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>'],
                    ['Financeiro', 'Contas a receber e a pagar em título + parcelas, com baixa parcial e renegociação.', '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
                    ['Caixa diário', 'Abertura, sangria, reforço e fechamento por dia — com lançamento retroativo.', '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
                    ['Estoque', 'Entradas, saídas e ajustes — a venda de produto baixa o estoque automaticamente.', '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>'],
                    ['Multi-empresa', 'Uma rede com várias empresas: catálogo compartilhado e operação isolada por unidade.', '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>'],
                ];
            @endphp
            @foreach ($recursos as $i => $r)
                <div class="mn-reveal group rounded-2xl border border-ink/10 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-700/5" style="transition-delay: {{ $i * 60 }}ms">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $r[2] !!}</svg>
                    </div>
                    <h3 class="mt-5 font-display text-xl font-bold text-ink">{{ $r[0] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $r[1] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────── Para quem ───────────────────────── --}}
    <section id="para-quem" class="border-y border-ink/5 bg-brand-50/40 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-5">
            <div class="mn-reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-bold uppercase tracking-widest text-brand-600">Para quem é</span>
                <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Feito para quem atende e vende todo dia</h2>
            </div>
            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['Clínicas', '🩺', 'Estética, fisioterapia, odontologia'], ['Salões & Barbearias', '✂️', 'Beleza, cabelo e estética'], ['Massoterapia', '💆', 'Massagem e terapias corporais'], ['Autônomos', '⭐', 'Profissionais que atendem por hora']] as $i => $p)
                    <div class="mn-reveal rounded-2xl bg-white p-6 shadow-sm ring-1 ring-ink/5" style="transition-delay: {{ $i * 60 }}ms">
                        <div class="text-3xl">{{ $p[1] }}</div>
                        <h3 class="mt-4 font-display text-lg font-bold text-ink">{{ $p[0] }}</h3>
                        <p class="mt-1 text-sm text-ink-soft">{{ $p[2] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Como funciona ───────────────────────── --}}
    <section id="como-funciona" class="mx-auto max-w-6xl px-5 py-20 sm:py-28">
        <div class="mn-reveal mx-auto max-w-2xl text-center">
            <span class="text-sm font-bold uppercase tracking-widest text-brand-600">Como funciona</span>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Comece em três passos</h2>
        </div>
        <div class="mt-14 grid gap-8 md:grid-cols-3">
            @foreach ([['01', 'Crie sua conta', 'A rede já nasce com categorias, serviços e clientes de exemplo para você explorar.'], ['02', 'Configure o catálogo', 'Cadastre serviços, produtos e sua equipe — com perfis de acesso por permissão.'], ['03', 'Atenda e acompanhe', 'Agende, venda e veja o financeiro e o caixa se atualizarem em tempo real.']] as $i => $s)
                <div class="mn-reveal relative" style="transition-delay: {{ $i * 80 }}ms">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 font-display text-lg font-extrabold text-white shadow-lg shadow-brand-600/25">{{ $s[0] }}</div>
                    <h3 class="mt-5 font-display text-xl font-bold text-ink">{{ $s[1] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $s[2] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────── CTA ───────────────────────── --}}
    <section class="mx-auto max-w-6xl px-5 pb-24">
        <div class="mn-reveal relative overflow-hidden rounded-3xl bg-brand-600 px-8 py-14 text-center shadow-2xl shadow-brand-700/30 sm:px-16 sm:py-20">
            <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-accent/30 blur-2xl"></div>
            <h2 class="relative font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Pronto para organizar seu negócio?</h2>
            <p class="relative mx-auto mt-4 max-w-xl text-lg text-brand-50">Crie sua conta e comece a usar agora — sem cartão, sem instalação.</p>
            <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('registrar') }}" class="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-base font-bold text-brand-700 shadow-lg transition hover:bg-brand-50">Criar conta grátis</a>
                <a href="{{ route('login') }}" class="inline-flex items-center rounded-full border border-white/40 px-7 py-3.5 text-base font-semibold text-white transition hover:bg-white/10">Entrar</a>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Footer ───────────────────────── --}}
    <footer class="border-t border-ink/5 bg-white">
        <div class="mx-auto max-w-6xl px-5 py-12">
            <div class="flex flex-col items-start justify-between gap-8 sm:flex-row">
                <div class="max-w-sm">
                    <div class="flex items-center gap-2.5">
                        @include('partials.logo-mark', ['size' => 30])
                        <span class="font-display text-base font-extrabold tracking-tight text-ink">Meu Negócio</span>
                    </div>
                    <p class="mt-3 text-sm text-ink-soft">Sistema de gestão multi-tenant para pequenos negócios. Projeto de portfólio, open source.</p>
                </div>
                <div class="flex gap-16">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-ink-soft">Produto</div>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="#recursos" class="text-ink transition hover:text-brand-600">Recursos</a></li>
                            <li><a href="#para-quem" class="text-ink transition hover:text-brand-600">Para quem é</a></li>
                            <li><a href="#como-funciona" class="text-ink transition hover:text-brand-600">Como funciona</a></li>
                        </ul>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest text-ink-soft">Acesso</div>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('login') }}" class="text-ink transition hover:text-brand-600">Entrar</a></li>
                            <li><a href="{{ route('registrar') }}" class="text-ink transition hover:text-brand-600">Criar conta</a></li>
                            <li><a href="{{ $github }}" target="_blank" rel="noopener" class="text-ink transition hover:text-brand-600">GitHub</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mt-10 border-t border-ink/5 pt-6 text-sm text-ink-soft">
                © {{ date('Y') }} Meu Negócio. Construído com Laravel.
            </div>
        </div>
    </footer>
</body>

</html>
