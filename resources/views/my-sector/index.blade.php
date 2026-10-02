{{-- Quem coordena mais de um setor escolhe aqui qual equipe quer ver. --}}
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Meus setores">
            Você coordena mais de um setor. Escolha qual equipe quer ver.
        </x-page-title>

        @if($sectors->count() > 6)
            <x-search-bar mode="client" target="#meus-setores" placeholder="Buscar setor" />
        @endif

        <div id="meus-setores" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach($sectors as $sector)
                <a href="{{ route('my-sector.show', $sector) }}" data-search=""
                   class="flex items-start gap-3 rounded-card bg-surface p-5 shadow-card transition hover:shadow-pop focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-grena-tint text-grena-ink">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-display text-base font-semibold tracking-tight text-ink">{{ $sector->name }}</span>
                        @if($sector->description)
                            <span class="mt-0.5 block text-sm text-ink-2">{{ $sector->description }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </x-page>
</x-app-layout>
