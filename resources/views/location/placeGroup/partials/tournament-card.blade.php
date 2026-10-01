@php
    $imageUrl = $tournament->image
        ? (Str::startsWith($tournament->image, 'http') ? $tournament->image : asset('images/' . $tournament->image))
        : null;
    $active = $tournament->status_id == 1;
@endphp
{{-- Editar/excluir vão para o torneio (antes apontavam para a rota de local, com o id do torneio). --}}
<x-card :href="route('tournaments.edit', $tournament->id)" data-search="{{ $tournament->title }}">
    <x-slot:media>
        <x-media :src="$imageUrl" :alt="'Imagem de ' . $tournament->title" area="reservas" icon="trophy" />
    </x-slot:media>

    <a href="{{ route('tournaments.edit', $tournament->id) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">{{ $tournament->title }}</a>
    <p class="font-mono text-sm font-semibold text-ink">R$ {{ number_format($tournament->price, 2, ',', '.') }}</p>

    <x-slot:footer>
        <x-pill :kind="$active ? 'ok' : 'off'">{{ $active ? 'Ativo' : 'Inativo' }}</x-pill>
        <div class="flex items-center gap-1">
            <x-secondary-button-a size="sm" href="{{ route('tournaments.edit', $tournament->id) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
            <form action="{{ route('tournaments.destroy', $tournament->id) }}" method="POST" onsubmit="return confirm('Excluir este torneio?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Excluir {{ $tournament->title }}" class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </x-slot:footer>
</x-card>
