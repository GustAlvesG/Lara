@php
    // Imagem salva só com o nome do arquivo mora em public/images.
    $imageUrl = $item->image_horizontal
        ? (str_contains($item->image_horizontal, 'http') ? $item->image_horizontal : asset('images/' . $item->image_horizontal))
        : null;
    $placeCount = count($item['places']);
    $minPrice = number_format((float) (collect($item['places'])->min('price') ?? 0), 2, ',', '.');
@endphp
<x-card :href="route('place-group.show', $item->id)" data-search="{{ $item['name'] }} {{ $item['category'] }}">
    <x-slot:media>
        <x-media :src="$imageUrl" :alt="'Imagem de ' . $item['name']" area="reservas" icon="calendar" />
    </x-slot:media>

    <a href="{{ route('place-group.show', $item->id) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">
        {{ $item['name'] }}
    </a>
    <p class="text-xs font-bold capitalize text-ink-3">{{ $item['category'] }}</p>

    <x-slot:footer>
        <span class="text-sm text-ink-2">{{ $placeCount }} {{ $placeCount === 1 ? 'local' : 'locais' }}</span>
        <span class="font-mono text-sm font-semibold text-ink">a partir de R$ {{ $minPrice }}<span class="font-sans text-xs font-normal text-ink-3">/h</span></span>
    </x-slot:footer>
</x-card>
