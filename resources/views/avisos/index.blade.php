@php
    $tab = request('tab') === 'todos' ? 'todos' : 'ativos';
    $tabClasses = 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
    $grid = 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page x-data="{ tab: '{{ $tab }}' }">
        <x-page-title title="Avisos e lembretes">
            @if ($search)
                {{ $todos->count() }} {{ $todos->count() === 1 ? 'resultado' : 'resultados' }} para “{{ $search }}”
            @else
                {{ $avisos->count() }} {{ $avisos->count() === 1 ? 'aviso ativo' : 'avisos ativos' }}
            @endif

            @auth
                <x-slot:actions>
                    <x-primary-button-a href="{{ route('avisos.create') }}">
                        <x-icon name="plus" /> Novo aviso
                    </x-primary-button-a>
                </x-slot:actions>
            @endauth
        </x-page-title>

        @if (session('success'))
            <div class="flex items-center gap-2 rounded-2xl bg-ok-soft px-4 py-3 text-sm font-semibold text-ok" role="status">
                <x-icon name="check" />{{ session('success') }}
            </div>
        @endif

        <x-search-bar placeholder="Buscar por título ou tag…" />

        <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Quais avisos">
            <button type="button" role="tab" @click="tab = 'ativos'" :aria-selected="(tab === 'ativos').toString()" class="{{ $tabClasses }}">
                Ativos <span class="font-mono text-[11px] text-ink-3">{{ $avisos->count() }}</span>
            </button>
            <button type="button" role="tab" @click="tab = 'todos'" :aria-selected="(tab === 'todos').toString()" class="{{ $tabClasses }}">
                Todos <span class="font-mono text-[11px] text-ink-3">{{ $todos->count() }}</span>
            </button>
        </div>

        {{-- Ativos --}}
        <div x-show="tab === 'ativos'" @if ($tab !== 'ativos') x-cloak @endif class="flex flex-col gap-4">
            @if ($avisos->isEmpty())
                <x-empty-state icon="bell">
                    @if ($search)
                        Nenhum aviso ativo corresponde a “{{ $search }}”.
                        <a href="{{ route('avisos.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                    @else
                        Nenhum aviso ativo no momento.
                    @endif
                </x-empty-state>
            @else
                <div class="{{ $grid }}">
                    @foreach ($avisos as $aviso)
                        @include('avisos.partials.card', ['aviso' => $aviso])
                    @endforeach
                </div>
            @endif

            {{-- Expirados recolhidos dentro de Ativos --}}
            @if ($expirados->isNotEmpty())
                <div x-data="{ open: false }" class="flex flex-col gap-4">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                        class="inline-flex w-fit items-center gap-2 text-sm font-bold text-ink-2 transition hover:text-ink">
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="open ? '' : '-rotate-90'" />
                        Expirados ({{ $expirados->count() }})
                    </button>
                    <div x-show="open" x-cloak class="{{ $grid }}">
                        @foreach ($expirados as $aviso)
                            @include('avisos.partials.card', ['aviso' => $aviso, 'expired' => true])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Todos --}}
        <div x-show="tab === 'todos'" @if ($tab !== 'todos') x-cloak @endif>
            @if ($todos->isEmpty())
                <x-empty-state icon="bell">
                    @if ($search)
                        Nenhum aviso corresponde a “{{ $search }}”.
                        <a href="{{ route('avisos.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                    @else
                        Nenhum aviso encontrado.
                    @endif
                </x-empty-state>
            @else
                <div class="{{ $grid }}">
                    @foreach ($todos as $aviso)
                        @include('avisos.partials.card', ['aviso' => $aviso])
                    @endforeach
                </div>
            @endif
        </div>
    </x-page>
</x-app-layout>
