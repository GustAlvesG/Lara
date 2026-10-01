@php
    $isExpired = $expired ?? $aviso->isExpired();
    $privacyLabels = [
        'pessoa' => 'Pessoal',
        'setor' => 'Setor',
        'publico' => 'Público',
        'grupo' => 'Grupo',
    ];
    $url = route('avisos.show', $aviso);
@endphp

<x-card :href="$url" :class="'transition hover:-translate-y-0.5' . ($isExpired ? ' opacity-60' : '')">
    <x-slot:media>
        {{-- Sem imagem, o substituto na cor do InfoClube; o arquivo que falhar
             ao carregar cai nele também. --}}
        <x-media :src="$aviso->image ? asset('images/avisos/' . $aviso->image) : null" :alt="$aviso->title" area="info" icon="bell" ratio="short">
            <x-media-tag>{{ $privacyLabels[$aviso->privacy] ?? $privacyLabels['setor'] }}</x-media-tag>
        </x-media>
    </x-slot:media>

    <div class="flex flex-wrap gap-1.5">
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

    <a href="{{ $url }}" class="text-[15.5px] font-bold leading-snug text-ink no-underline hover:underline">
        <span class="line-clamp-2">{{ $aviso->title }}</span>
    </a>

    @if ($aviso->content)
        <p class="line-clamp-3 text-[13.5px] text-ink-2">{!! strip_tags($aviso->content) !!}</p>
    @endif

    @if ($aviso->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach ($aviso->tags as $tag)
                <a href="{{ route('avisos.index', ['q' => $tag->name]) }}"
                    class="rounded-full bg-area-info px-2 py-0.5 text-xs font-semibold text-area-info-ink no-underline hover:underline">#{{ $tag->name }}</a>
            @endforeach
        </div>
    @endif

    <x-slot:footer>
        <span class="min-w-0 truncate text-xs text-ink-3">{{ $aviso->creator->name ?? '—' }} · {{ $aviso->created_at->diffForHumans() }}</span>

        @auth
            <div class="flex items-center gap-1">
                <a href="{{ route('avisos.edit', $aviso) }}" title="Editar" aria-label="Editar {{ $aviso->title }}"
                    class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
                    <x-icon name="pencil" />
                </a>
                <form action="{{ route('avisos.destroy', $aviso) }}" method="POST" onsubmit="return confirm('Remover este aviso?')">
                    @csrf @method('DELETE')
                    <button type="submit" title="Remover" aria-label="Remover {{ $aviso->title }}"
                        class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                        <x-icon name="trash" />
                    </button>
                </form>
            </div>
        @endauth
    </x-slot:footer>
</x-card>
