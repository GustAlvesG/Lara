{{--
    Porta de entrada para quem ainda não entrou (10/11/2026): a sede do clube
    em tela cheia com o caminho para entrar e, abaixo, os módulos do Lara,
    cada um na sua cor. Página própria, fora do layout do painel — não há menu
    para mostrar. Segue o padrão das telas de entrar e de criar conta
    (<x-auth-layout>): mesma foto, mesmo movimento (partials/entrada-estilos).

    A lista acompanha o menu (App\View\Navigation): módulo novo no menu entra
    aqui também, na mesma cor de área.
--}}
@php
    $modules = [
        ['info', 'info', 'InfoClube', 'Informações e avisos do clube num lugar só.'],
        ['portaria', 'car', 'SIV', 'Veículos na portaria, placas da diretoria e frota.'],
        ['inicio', 'home', 'Home Assistant', 'Automações do clube pelo Home Assistant.'],
        ['reservas', 'calendar', 'Reservas', 'Agenda dos espaços, horários e pagamentos.'],
        ['reservas', 'ticket', 'Ingressos', 'Eventos vendidos no site, lotes e check-in.'],
        ['externos', 'users', 'Externos', 'Empresas parceiras, liberações e carros de aplicativo.'],
        ['lara', 'chat', 'HAL (IA)', 'Assistente que responde sobre o clube e o Lara.'],
        ['lara', 'chat', 'Bot WhatsApp', 'Fluxos de resposta das conversas no WhatsApp.'],
        ['compras', 'bag', 'Compras', 'Mapas de cotação das solicitações de compra.'],
        ['cartao', 'card', 'Carteirinhas', 'Emissão de carteirinhas a partir de modelos.'],
        ['cartao', 'pencil', 'Assinaturas', 'Documentos assinados no balcão, no tablet ou por link.'],
        ['cartao', 'doc', 'Contratos', 'Redação, validação e aditivos dos contratos.'],
        ['freela', 'user', 'Freelancers', 'Cadastro, contratos, assinaturas e pagamentos.'],
        ['freela', 'users', 'Funcionários', 'Cadastro do RH: matrícula, CPF e setor.'],
        ['placar', 'trophy', 'Placar Clube', 'Equipes, jogos, competições e súmulas.'],
        ['placar', 'trophy', 'Torneios', 'Torneios vendidos no site, inscrições e chaves.'],
        ['placar', 'monitor', 'RePET', 'Replays e transmissões das quadras.'],
        ['info', 'mail', 'Disparo de E-mails', 'Campanhas de e-mail para listas e sócios.'],
    ];
    $count = count($modules);
    $photo = 'images/auth/sede-social.jpg';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="O Lara é o sistema interno do Clube dos Funcionários: {{ $count }} módulos, da portaria às reservas.">
        <meta name="theme-color" content="#8A1538">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        <title>{{ config('app.name', 'Lara') }} · Clube dos Funcionários</title>

        @include('partials.theme-script')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700&display=swap" rel="stylesheet" />
        <link rel="preload" as="image" href="{{ asset($photo) }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.entrada-estilos')
    </head>
    <body class="flex min-h-screen flex-col bg-canvas font-sans text-ink antialiased">
        <a href="#modulos" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-surface focus:px-4 focus:py-2 focus:font-bold focus:text-ink focus:shadow-pop">
            Pular para os módulos
        </a>

        {{-- Primeira tela: a sede do clube, o que é o Lara e o caminho para entrar. --}}
        <section class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-ink text-white" aria-labelledby="titulo">
            <img src="{{ asset($photo) }}" alt="Área coberta da sede social do Clube dos Funcionários à noite, com mesas de madeira e sócios conversando"
                 class="auth-photo-img absolute inset-0 -z-10 h-full w-full object-cover" style="object-position: 55% 50%"
                 fetchpriority="high" decoding="async">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/85 via-black/35 to-black/45" aria-hidden="true"></div>

            <header class="mx-auto flex w-full max-w-[1200px] items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 rounded-xl text-white no-underline focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60" aria-label="LARA">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white shadow-card">
                        <x-application-logo :width="'21px'" :height="'26px'" :color="'#A00001'" />
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight">LARA</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-1 sm:gap-2" aria-label="Acesso">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="auth-submit inline-flex h-11 items-center gap-2 rounded-full bg-grena px-5 text-sm font-bold text-white no-underline hover:bg-grena-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                                Ir para o painel
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex h-11 items-center rounded-full px-4 text-sm font-bold text-white no-underline transition hover:bg-white/10 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                                    Criar conta
                                </a>
                            @endif
                            <a href="{{ route('login') }}" class="auth-submit inline-flex h-11 items-center rounded-full bg-white px-5 text-sm font-bold text-grena no-underline hover:bg-white/90 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                                Entrar
                            </a>
                        @endauth
                    </nav>
                @endif
            </header>

            <div class="mx-auto flex w-full max-w-[1200px] flex-1 items-end px-4 pb-12 pt-16 sm:px-6 sm:pb-16 lg:px-8 lg:pb-20">
                <div class="auth-enter max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/75">Clube dos Funcionários</p>
                    <h1 id="titulo" class="mt-3 font-display text-[34px] font-semibold leading-[1.08] tracking-tight sm:text-5xl lg:text-[64px]">
                        O sistema do Clube, feito pelo Clube.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg">
                        O Lara reúne o dia a dia do clube em {{ $count }} módulos, da portaria às reservas, dos avisos às compras.
                        Você entra com a sua matrícula ou o seu e-mail.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="auth-submit inline-flex h-12 items-center justify-center gap-2 rounded-full bg-grena px-7 text-base font-bold text-white no-underline hover:bg-grena-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                                Abrir o painel <x-icon name="arrow-right" class="h-5 w-5" />
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="auth-submit inline-flex h-12 items-center justify-center gap-2 rounded-full bg-grena px-7 text-base font-bold text-white no-underline hover:bg-grena-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                                Entrar no Lara <x-icon name="arrow-right" class="h-5 w-5" />
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="auth-link inline-flex min-h-11 items-center justify-center self-center text-base font-bold text-white sm:self-auto">Ainda não tenho conta</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </section>

        {{-- Segunda tela: os módulos, cada um na cor da sua área. --}}
        <main id="modulos" class="mx-auto w-full max-w-[1200px] flex-1 px-4 py-16 sm:px-6 sm:py-20 lg:px-8" aria-labelledby="modulos-titulo">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-grena-ink">Dentro do Lara</p>
                <h2 id="modulos-titulo" class="mt-2 font-display text-[28px] font-semibold leading-tight tracking-tight text-ink sm:text-4xl">
                    {{ $count }} módulos, cada um na sua cor.
                </h2>
                <p class="mt-3 text-base leading-relaxed text-ink-2 sm:text-lg">
                    O menu muda de pessoa para pessoa: cada funcionário vê os módulos que o setor dele usa.
                    Falta algum? Peça ao coordenador do seu setor.
                </p>
            </div>

            <ul class="mt-10 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                @foreach ($modules as [$area, $glyph, $name, $text])
                    <li class="relative flex flex-col gap-3 overflow-hidden rounded-card p-5" style="{{ \App\View\AreaColor::style($area) }}">
                        <x-icon :name="$glyph" class="pointer-events-none absolute -right-4 -top-5 h-24 w-24 opacity-[.12]" />
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-surface" style="color: rgb(var(--ci))">
                            <x-icon :name="$glyph" class="h-5 w-5" />
                        </span>
                        <div class="relative">
                            <h3 class="font-display text-base font-semibold tracking-tight">{{ $name }}</h3>
                            <p class="mt-1 text-sm opacity-90">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </main>

        @include('partials.footer')
    </body>
</html>
