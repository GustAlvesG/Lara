@php
    $imageUrl = $place->image
        ? (Str::startsWith($place->image, 'http') ? $place->image : asset('images/' . $place->image))
        : null;
    $active = $place->status_id == 1;
@endphp
<x-card :href="route('place-group.editPlace', $place->id)" data-search="{{ $place->name }}">
    <x-slot:media>
        <x-media :src="$imageUrl" :alt="'Imagem de ' . $place->name" area="reservas" icon="calendar" />
    </x-slot:media>

    <a href="{{ route('place-group.editPlace', $place->id) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">{{ $place->name }}</a>
    <p class="font-mono text-sm font-semibold text-ink">R$ {{ number_format($place->price, 2, ',', '.') }}<span class="font-sans text-xs font-normal text-ink-3">/h</span></p>

    <x-slot:footer>
        <x-pill :kind="$active ? 'ok' : 'off'">{{ $active ? 'Ativo' : 'Inativo' }}</x-pill>
        <div class="flex items-center gap-1">
            <x-secondary-button-a size="sm" href="{{ route('place-group.editPlace', $place->id) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
            <form action="{{ route('place-group.destroyPlace', $place->id) }}" method="POST" onsubmit="return confirm('Excluir este local?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Excluir {{ $place->name }}" class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </x-slot:footer>
</x-card>
