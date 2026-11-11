@props([
    'title',
    // Sem foto informada, sorteia uma das fotos do clube a cada abertura
    // (App\View\ClubPhotos, 11/11/2026). Entrar e criar conta usam o sorteio.
    'photo' => null,
    'photoAlt' => null,
    'photoPosition' => 'center',
    'tagline' => null,
])
@php
    $foto = $photo
        ? ['src' => $photo, 'alt' => $photoAlt ?? '', 'position' => $photoPosition, 'drift' => ['0%', '0%']]
        : \App\View\ClubPhotos::random();
@endphp

{{--
    Casca das telas de entrar e de criar conta (10/11/2026): foto real do
    clube de um lado, formulário do outro. No celular a foto vira uma faixa
    curta no topo, com o logo, e o formulário vem logo abaixo.

    As fotos ficam em public/images/auth/ — cópias recortadas (sem a marca
    d'água) e comprimidas, para a tela não depender de uma imagem que alguém
    pode apagar pelo sistema.

    A entrada, o sublinhado dos links e o passo do botão vêm de
    partials/entrada-estilos (o mesmo da página inicial).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#8A1538">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>{{ $title }} · {{ config('app.name', 'Lara') }}</title>
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700&display=swap" rel="stylesheet" />
    <link rel="preload" as="image" href="{{ asset($foto['src']) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.entrada-estilos')
    <style>
        /* Na faixa de 1024–1279 a coluna é estreita: o rodapé empilha. */
        @media (min-width: 1024px) and (max-width: 1279px) {
            .auth-footer footer > div { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,11fr)_minmax(0,10fr)]">

        {{-- Foto do clube. A camada escura garante a leitura do logo e da frase. --}}
        <aside class="relative isolate h-44 overflow-hidden bg-ink sm:h-60 lg:sticky lg:top-0 lg:h-screen">
            <img src="{{ asset($foto['src']) }}" alt="{{ $foto['alt'] }}"
                 class="auth-photo-img auth-photo-drift absolute inset-0 -z-10 h-full w-full object-cover"
                 style="{{ \App\View\ClubPhotos::style($foto) }}"
                 data-club-photo="{{ $foto['key'] ?? 'fixa' }}"
                 fetchpriority="high" decoding="async">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/80 via-black/25 to-black/35" aria-hidden="true"></div>

            <div class="flex h-full flex-col justify-between p-5 sm:p-8 lg:p-12">
                <a href="{{ url('/') }}" class="inline-flex w-fit items-center gap-2.5 rounded-xl text-white no-underline focus:outline-none focus-visible:ring-4 focus-visible:ring-white/60">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white shadow-card">
                        <x-application-logo :width="'21px'" :height="'26px'" :color="'#A00001'" />
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight">LARA</span>
                    <span class="sr-only">— página inicial</span>
                </a>

                @if ($tagline)
                    <div class="auth-tagline hidden max-w-lg lg:block">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/75">Clube dos Funcionários</p>
                        <p class="mt-3 font-display text-[40px] font-semibold leading-[1.1] tracking-tight text-white xl:text-[46px]">{{ $tagline }}</p>
                    </div>
                @endif
            </div>
        </aside>

        <div class="flex min-w-0 flex-col bg-surface">
            <main id="conteudo" class="flex flex-1 justify-center px-4 py-8 sm:px-10 sm:py-12 lg:items-center lg:px-14">
                <div class="w-full max-w-[440px]">
                    {{ $slot }}
                </div>
            </main>

            <div class="auth-footer">
                @include('partials.footer')
            </div>
        </div>
    </div>

    <script>
        // Mostrar/ocultar senha e aviso de Caps Lock dos campos x-password-input.
        (function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                var input = document.getElementById(button.getAttribute('aria-controls'));
                if (!input) return;

                var show = button.querySelector('[data-eye="show"]');
                var hide = button.querySelector('[data-eye="hide"]');

                button.addEventListener('click', function () {
                    var visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
                    button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
                    show.hidden = visible;
                    hide.hidden = !visible;
                    input.focus();
                });

                // Ao enviar, a senha volta a ficar oculta (o navegador não a guarda à vista).
                if (input.form) {
                    input.form.addEventListener('submit', function () { input.type = 'password'; });
                }

                var warning = document.getElementById(input.id + '-caps');
                if (!warning) return;

                function caps(event) {
                    if (typeof event.getModifierState === 'function') {
                        warning.hidden = !event.getModifierState('CapsLock');
                    }
                }

                input.addEventListener('keydown', caps);
                input.addEventListener('keyup', caps);
                input.addEventListener('blur', function () { warning.hidden = true; });
            });
        })();
    </script>
</body>
</html>
