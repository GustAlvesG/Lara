{{-- Mapas de lugar marcado do evento (mesas, bistrôs, camarotes): lista,
     novo mapa (em branco ou copiando outro) e o caminho para desenhar. O tipo
     de ingresso que aponta para o mapa vende os lugares dele. --}}
@php
    use App\Models\Ticket\TicketMap;

    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Mapas de lugares — ' . $event->title" :back="route('tickets.show', $event)">
            Mesas, bistrôs e camarotes que quem compra escolhe no mapa. Desenhe os lugares e as áreas de orientação (palco, piscina, banheiros); depois, na tela do evento, crie o tipo de ingresso e escolha o mapa dele. O ingresso do lugar não é entrada: cada pessoa compra a sua.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @forelse ($event->maps as $map)
            <article class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-ink">{{ $map->name }}</h2>
                    <p class="text-sm text-ink-3">{{ $map->seats_count }} lugar(es) · {{ count($map->areas ?? []) }} área(s) · {{ $map->width }} × {{ $map->height }}</p>
                    <p class="text-xs text-ink-3">
                        @if ($map->types->isEmpty())
                            Nenhum tipo de ingresso vende este mapa ainda.
                        @else
                            Vendido por: {{ $map->types->map(fn ($t) => $t->fullName() . ($t->seat_category ? " (categoria {$t->seat_category})" : ''))->implode('; ') }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-secondary-button-a href="{{ route('tickets.maps.edit', [$event, $map]) }}"><x-icon name="pencil" /> Desenhar</x-secondary-button-a>
                    <x-secondary-button-a href="{{ route('tickets.maps.captives.index', [$event, $map]) }}"><x-icon name="star" /> Mesas cativas</x-secondary-button-a>
                    @can(\App\Authorization\Permissions::INGRESSOS_VENDAS)
                        <x-secondary-button-a href="{{ route('tickets.maps.occupancy', [$event, $map]) }}"><x-icon name="eye" /> Ocupação</x-secondary-button-a>
                    @endcan
                    @if ($map->types->isEmpty())
                        <form method="POST" action="{{ route('tickets.maps.destroy', [$event, $map]) }}" onsubmit="return confirm('Apagar o mapa {{ addslashes($map->name) }} com os lugares dele?')">
                            @csrf @method('DELETE')
                            <x-danger-button size="sm">Apagar</x-danger-button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state icon="grid">Nenhum mapa neste evento. Crie o primeiro abaixo (ex.: Mesas, Bistrôs, Camarotes).</x-empty-state>
        @endforelse

        <section class="rounded-card bg-surface p-5 shadow-card">
            <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Novo mapa</h2>
            <form method="POST" action="{{ route('tickets.maps.store', $event) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                @csrf
                <div class="sm:col-span-2"><x-input-label for="map-name" value="Nome" /><x-text-input id="map-name" name="name" class="mt-1" required maxlength="100" placeholder="Ex.: Mesas" :value="old('name')" /></div>
                <div class="sm:col-span-2">
                    <x-input-label for="map-copy" value="Copiar lugares e áreas de (opcional)" />
                    <select id="map-copy" name="copy_from" class="{{ $select }}">
                        <option value="">Começar em branco</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected((int) old('copy_from') === $source->id)>{{ $source->event->title }} · {{ $source->name }} ({{ $source->seats_count }} lugares)</option>
                        @endforeach
                    </select>
                </div>
                <div><x-input-label for="map-width" value="Largura (unidades)" /><x-text-input type="number" id="map-width" name="width" class="mt-1" :value="old('width')" placeholder="1000" min="{{ TicketMap::MIN_SIZE }}" max="{{ TicketMap::MAX_SIZE }}" /></div>
                <div><x-input-label for="map-height" value="Altura (unidades)" /><x-text-input type="number" id="map-height" name="height" class="mt-1" :value="old('height')" placeholder="600" min="{{ TicketMap::MIN_SIZE }}" max="{{ TicketMap::MAX_SIZE }}" /></div>
                <div><x-input-label for="map-bg" value="Imagem de fundo (opcional)" /><input type="file" id="map-bg" name="background" accept="image/*" class="mt-1 block w-full text-sm text-ink-2"></div>
                <div><x-input-label for="map-bg-url" value="Ou link da imagem" /><x-text-input id="map-bg-url" name="background_url" class="mt-1" :value="old('background_url')" placeholder="https://i.imgur.com/…" /></div>
                <p class="text-xs text-ink-3 sm:col-span-4">A imagem (a planta do salão, por exemplo) fica por baixo do desenho. Largura e altura em branco: 1000 × 600 (ou as do mapa copiado); a imagem em branco também vem do mapa copiado. Depois de criado, desenhe os lugares na tela do mapa.</p>
                <div class="sm:col-span-4 sm:text-right"><x-primary-button>Criar mapa</x-primary-button></div>
            </form>
        </section>
    </x-page>
</x-app-layout>
