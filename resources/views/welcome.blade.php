{{--
    Porta de entrada para quem ainda não entrou: o que é o LARA, as áreas do
    clube que ele atende (cada uma na sua cor) e o caminho para entrar.
    Página própria, fora do layout do painel — não há menu para mostrar.
--}}
@php
    $areas = [
        ['portaria', 'car', 'SIV', 'Identificação de veículos na portaria, placas da diretoria e frota.'],
        ['info', 'info', 'InfoClube', 'Informações e avisos do clube em um só lugar.'],
        ['reservas', 'calendar', 'Reservas', 'Agenda dos espaços, horários disponíveis e pagamentos.'],
        ['externos', 'users', 'Externos', 'Empresas parceiras, liberações pontuais e carros de aplicativo.'],
        ['freela', 'user', 'Freelancers', 'Cadastro, contratos, assinaturas e pagamentos.'],
        ['placar', 'trophy', 'Placar Clube', 'Equipes, jogos, competições e súmulas.'],
        ['compras', 'bag', 'Compras', 'Mapas de cotação das solicitações de compra.'],
        ['cartao', 'card', 'Carteirinhas', 'Emissão de carteirinhas a partir de modelos.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        <title>{{ config('app.name', 'Lara') }}</title>

        @include('partials.theme-script')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-canvas font-sans text-ink antialiased">

        <header class="mx-auto flex w-full max-w-[1200px] items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 no-underline" aria-label="LARA">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow-card">
                    <x-application-logo :width="'20px'" :height="'25px'" :color="'#A00001'" />
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-ink">LARA</span>
            </a>

            @if (Route::has('login'))
                <nav class="flex items-center gap-2" aria-label="Acesso">
                    @auth
                        <x-primary-button-a href="{{ url('/dashboard') }}">Ir para o painel <x-icon name="arrow-right" /></x-primary-button-a>
                    @else
                        @if (Route::has('register'))
                            <x-secondary-button-a href="{{ route('register') }}">Registrar-se</x-secondary-button-a>
                        @endif
                        <x-primary-button-a href="{{ route('login') }}">Entrar</x-primary-button-a>
                    @endauth
                </nav>
            @endif
        </header>

        <main class="mx-auto flex w-full max-w-[1200px] flex-1 flex-col gap-10 px-4 pb-6 pt-6 sm:px-6 sm:pt-12 lg:px-8">

            <section class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.1em] text-ink-3">Clube dos Funcionários</p>
                <h1 class="mt-3 font-display text-3xl font-semibold leading-tight tracking-tight text-ink sm:text-[44px]">
                    O sistema do Clube, feito pelo Clube.
                </h1>
                <p class="mt-4 max-w-2xl text-base text-ink-2 sm:text-lg">
                    O Lara reúne as ferramentas do dia a dia do clube: da portaria às reservas, dos avisos às compras.
                    Cada módulo tem o seu lugar, com a sua cor.
                </p>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @auth
                        <x-primary-button-a href="{{ url('/dashboard') }}" class="h-12 px-6 text-base">Ir para o painel <x-icon name="arrow-right" /></x-primary-button-a>
                    @else
                        <x-primary-button-a href="{{ route('login') }}" class="h-12 px-6 text-base">Entrar <x-icon name="arrow-right" /></x-primary-button-a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="text-sm font-bold text-grena-ink hover:underline">Ainda não tenho conta</a>
                        @endif
                    @endauth
                </div>
            </section>

            <section aria-labelledby="areas-titulo">
                <h2 id="areas-titulo" class="mb-3 text-xs font-bold uppercase tracking-[0.1em] text-ink-3">O que tem aqui dentro</h2>

                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($areas as [$area, $glyph, $name, $text])
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

                
            </section>
        </main>

        @include('partials.footer')
    </body>
</html>
