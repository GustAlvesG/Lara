{{--
    Aviso de leitura obrigatória, em tela cheia. Página própria, sem o menu:
    enquanto a pessoa não confirmar, o middleware `avisos_obrigatorios` traz
    toda navegação para cá — então não há para onde o menu levaria. As únicas
    saídas são confirmar a leitura ou sair do sistema.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        <title>Aviso de leitura obrigatória · {{ config('app.name', 'Lara') }}</title>

        @include('partials.theme-script')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .aviso-content b, .aviso-content strong { font-weight: 700; }
            .aviso-content i, .aviso-content em { font-style: italic; }
            .aviso-content u { text-decoration: underline; }
            .aviso-content p + p, .aviso-content ul, .aviso-content ol { margin-top: .75rem; }
            .aviso-content ul { list-style: disc; padding-left: 1.25rem; }
            .aviso-content ol { list-style: decimal; padding-left: 1.25rem; }
            .aviso-content a { color: rgb(var(--grena-ink)); text-decoration: underline; }
        </style>
    </head>
    <body class="flex min-h-screen flex-col bg-canvas font-sans text-ink antialiased" x-data="{ ciente: false }">

        {{-- Faixa fixa: o que é esta tela e a única outra saída (sair). --}}
        <header class="sticky top-0 z-10 border-b border-line bg-surface">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl" style="{{ \App\View\AreaColor::style('info') }}">
                        <x-icon name="bell" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-display text-base font-semibold tracking-tight text-ink">Aviso de leitura obrigatória</p>
                        <p class="truncate text-xs text-ink-2">
                            @if ($restantes > 1)
                                {{ $restantes }} avisos aguardando a sua confirmação
                            @else
                                Confirme a leitura para continuar usando o sistema
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <x-secondary-button size="sm" type="submit"><x-icon name="logout" /> Sair</x-secondary-button>
                </form>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5 px-4 py-6 sm:px-6 sm:py-10">
            <article class="overflow-hidden rounded-card bg-surface shadow-card">
                @if ($aviso->image)
                    <img src="{{ asset('images/avisos/' . $aviso->image) }}" alt="" class="max-h-72 w-full object-cover" onerror="this.remove()">
                @endif

                <div class="flex flex-col gap-4 p-5 sm:p-8">
                    <h1 class="font-display text-2xl font-semibold leading-tight tracking-tight text-ink sm:text-3xl">{{ $aviso->title }}</h1>

                    @if ($aviso->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($aviso->tags as $tag)
                                <span class="rounded-full bg-subtle px-2.5 py-1 text-xs font-bold text-ink-2">#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($aviso->content)
                        <div class="aviso-content max-w-none text-base leading-relaxed text-ink">
                            {!! $aviso->content !!}
                        </div>
                    @endif

                    <p class="border-t border-line pt-4 text-sm text-ink-3">
                        Publicado por <span class="font-bold text-ink-2">{{ $aviso->creator->name ?? '—' }}</span>
                        em <span class="font-mono text-xs">{{ $aviso->created_at?->format('d/m/Y H:i') }}</span>
                    </p>
                </div>
            </article>
        </main>

        {{-- Confirmação: presa ao pé da tela, sempre à vista. --}}
        <footer class="sticky bottom-0 border-t border-line bg-surface">
            <form action="{{ route('avisos.acknowledge', $aviso) }}" method="POST"
                  class="mx-auto flex max-w-3xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                @csrf

                <div class="min-w-0">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="confirm" value="1" x-model="ciente"
                               class="mt-0.5 h-5 w-5 rounded border-line-strong text-grena focus:ring-grena-tint">
                        <span class="text-sm text-ink">
                            Li e estou ciente deste aviso.
                            <span class="block text-xs text-ink-3">A confirmação fica registrada com o seu usuário, a data e a hora.</span>
                        </span>
                    </label>
                    @error('confirm')
                        <p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <x-primary-button class="h-12 shrink-0 px-6 text-base" x-bind:disabled="!ciente">
                    <x-icon name="check" />
                    {{ $restantes > 1 ? 'Confirmar e ver o próximo' : 'Confirmar leitura' }}
                </x-primary-button>
            </form>
        </footer>
    </body>
</html>
