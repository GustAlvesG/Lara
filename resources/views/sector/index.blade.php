{{-- Setores e o que cada um alcança. A lista vem inteira: a busca filtra na página. --}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Setores" :back="route('users.index')">
            Quem está em cada setor e o que cada setor alcança no sistema.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('sectors.audit') }}"><x-icon name="history" /> Histórico de acesso</x-secondary-button-a>
                <x-primary-button-a href="{{ route('sectors.create') }}"><x-icon name="plus" /> Novo setor</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($sectors->isEmpty())
            <x-empty-state icon="users">
                Nenhum setor cadastrado.
                <a href="{{ route('sectors.create') }}" class="font-bold text-grena-ink hover:underline">Criar o primeiro setor</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#sectors-container" placeholder="Buscar setor" />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2" id="sectors-container">
                @foreach($sectors as $sector)
                    <article data-search="{{ $sector->full_access ? 'acesso total' : '' }}" class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('sectors.show', $sector->id) }}" class="group flex min-w-0 items-start gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-grena-tint text-grena-ink">
                                    <x-icon name="users" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-display text-base font-semibold tracking-tight text-ink group-hover:text-grena-ink">{{ $sector->name }}</span>
                                    @if($sector->description)
                                        <span class="block text-sm text-ink-2">{{ $sector->description }}</span>
                                    @endif
                                </span>
                            </a>

                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('sectors.show', $sector->id) }}" aria-label="Editar {{ $sector->name }}"
                                   class="grid h-9 w-9 place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form method="POST" action="{{ route('sectors.destroy', $sector->id) }}"
                                      onsubmit="return confirm('Excluir o setor \'{{ $sector->name }}\' permanentemente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="Excluir {{ $sector->name }}"
                                            class="grid h-9 w-9 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3 text-xs text-ink-3">
                            <span>
                                {{ $sector->users_count }} {{ $sector->users_count == 1 ? 'membro' : 'membros' }} ·
                                {{ $sector->full_access ? 'todas as permissões' : $sector->permissions_count . ($sector->permissions_count == 1 ? ' permissão' : ' permissões') }}
                            </span>
                            @if($sector->full_access)
                                <x-pill kind="info">Acesso total</x-pill>
                            @else
                                <span class="font-mono">#{{ $sector->id }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>
