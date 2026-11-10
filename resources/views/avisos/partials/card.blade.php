@php
    /*
     | Cartão de aviso (refeito em 10/11/2026). O cartão inteiro abre o aviso
     | pelo link do título (after:inset-0); tags, editar e remover ficam por
     | cima (z-10), em links/botões próprios.
     |
     | $featured: o destaque da lista (largo, foto ao lado no sm+).
     */
    $isExpired = $expired ?? $aviso->isExpired();
    $featured = $featured ?? false;
    $privacyLabels = [
        'pessoa' => 'Pessoal',
        'setor' => 'Setor',
        'publico' => 'Público',
        'grupo' => 'Grupo',
    ];
    $url = route('avisos.show', $aviso);
    $excerpt = $aviso->content ? \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags($aviso->content))), $featured ? 320 : 160) : null;
@endphp

<article class="group relative flex min-w-0 flex-col overflow-hidden rounded-card bg-surface shadow-card transition duration-200 hover:-translate-y-0.5 hover:shadow-pop focus-within:ring-4 focus-within:ring-grena-tint motion-reduce:hover:translate-y-0
    {{ $featured ? 'sm:col-span-2 sm:flex-row' : '' }} {{ $isExpired ? 'opacity-60' : '' }}">
    {{-- Sem imagem, o substituto na cor do InfoClube; o arquivo que falhar
         ao carregar cai nele também. --}}
    <x-media :src="$aviso->image ? asset('images/avisos/' . $aviso->image) : null" :alt="$aviso->title" area="info" icon="bell"
             :ratio="$featured ? 'video' : 'short'" zoom
             :class="$featured ? 'sm:aspect-auto sm:w-1/2 sm:shrink-0' : ''">
        <x-media-tag>{{ $privacyLabels[$aviso->privacy] ?? $privacyLabels['setor'] }}</x-media-tag>
    </x-media>

    <div class="flex flex-1 flex-col gap-2.5 p-4 pt-3.5 {{ $featured ? 'sm:p-6' : '' }}">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="font-mono text-xs text-ink-3">{{ $aviso->created_at->format('d/m/Y') }}</span>
            @if ($aviso->mandatory)
                <x-pill kind="warn" :icon="false">Leitura obrigatória</x-pill>
            @endif
            @if ($isExpired)
                <x-pill kind="off">Expirado</x-pill>
            @elseif ($aviso->expiresSoon())
                <x-pill kind="warn">Expira em breve</x-pill>
            @endif
            @if ($aviso->lembretes->isNotEmpty())
                <x-pill kind="info" :icon="false">
                    <x-icon name="bell" class="h-3 w-3" />
                    {{ $aviso->lembretes->count() }} {{ $aviso->lembretes->count() === 1 ? 'lembrete' : 'lembretes' }}
                </x-pill>
            @endif
        </div>

        <h3 class="font-display font-semibold leading-snug tracking-tight text-ink {{ $featured ? 'text-xl sm:text-2xl' : 'text-[17px]' }}">
            <a href="{{ $url }}" class="line-clamp-2 no-underline after:absolute after:inset-0 after:content-[''] focus:outline-none">{{ $aviso->title }}</a>
        </h3>

        @if ($excerpt)
            <p class="{{ $featured ? 'line-clamp-4 text-[15px]' : 'line-clamp-3 text-sm' }} text-ink-2">{{ $excerpt }}</p>
        @endif

        @if ($aviso->tags->isNotEmpty())
            <div class="relative z-10 flex flex-wrap gap-1">
                @foreach ($aviso->tags as $tag)
                    <a href="{{ route('avisos.index', ['q' => $tag->name]) }}"
                       class="inline-flex min-h-7 items-center rounded-full bg-area-info px-2.5 text-xs font-bold text-area-info-ink no-underline transition hover:-translate-y-px">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex items-center justify-between gap-2 border-t border-line pt-3">
            <span class="min-w-0 truncate text-xs text-ink-3">{{ $aviso->creator->name ?? '—' }} · {{ $aviso->created_at->diffForHumans() }}</span>

            @auth
                <div class="relative z-10 flex items-center gap-1">
                    <a href="{{ route('avisos.edit', $aviso) }}" title="Editar" aria-label="Editar {{ $aviso->title }}"
                       class="grid h-10 w-10 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                        <x-icon name="pencil" />
                    </a>
                    <form action="{{ route('avisos.destroy', $aviso) }}" method="POST" onsubmit="return confirm('Remover este aviso?')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Remover" aria-label="Remover {{ $aviso->title }}"
                                class="grid h-10 w-10 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                            <x-icon name="trash" />
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </div>
</article>
