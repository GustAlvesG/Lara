{{--
    Cartão de um contator: estado agora, motivo, linha do tempo e controle.
    Parâmetros:
      $contactor  Contactor
      $state      App\Services\HomeAssistant\ContactorState
      $timeline   array
      $now        Carbon
--}}
@php
    [$badgeLabel, $badgeClass] = match ($state->source) {
        \App\Services\HomeAssistant\ContactorState::SOURCE_QUICK       => ['Manual',      'bg-grena-tint text-grena-ink'],
        \App\Services\HomeAssistant\ContactorState::SOURCE_OVERRIDE    => ['Agendamento', 'bg-grena-tint text-grena-ink'],
        \App\Services\HomeAssistant\ContactorState::SOURCE_RESERVATION => ['Reserva',     'bg-[rgb(var(--a-reservas))] text-[rgb(var(--a-reservas-ink))]'],
        default                            => ['Automático',  'bg-subtle text-ink-2'],
    };

    $schedulesCount = $contactor->overrides->filter(fn ($o) => ! $o->is_quick && $o->is_active && ! $o->is_expired)->count();

    // Controle: automático (sem ação rápida), ligado ou desligado manualmente
    $control = $state->isManual() ? ($state->on ? 'on' : 'off') : 'auto';
@endphp

<article data-search="{{ $contactor->name }} {{ $contactor->entity_id }}" class="group bg-surface rounded-card shadow-card border transition
    {{ $state->on ? 'border-ok/40' : 'border-line' }}">

    <div class="p-5 space-y-4">
        {{-- Cabeçalho --}}
        <div class="flex items-start gap-3">
            <div class="relative w-11 h-11 rounded-xl flex items-center justify-center shrink-0 transition
                {{ $state->on
                    ? 'bg-ok text-white dark:text-canvas'
                    : 'bg-subtle text-ink-3' }}">
                <svg class="w-6 h-6" fill="{{ $state->on ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $state->on ? '1' : '2' }}" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h4 class="font-bold text-ink truncate" title="{{ $contactor->name }}">{{ $contactor->name }}</h4>
                </div>
                <p class="text-xs text-ink-3 font-mono truncate" title="{{ $contactor->entity_id }}">{{ $contactor->entity_id }}</p>
            </div>

            {{-- Menu --}}
            <div class="relative shrink-0" x-data="{ menu: false, copied: false }" @click.outside="menu = false" @keydown.escape="menu = false">
                <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-label="Mais ações"
                    class="p-1.5 -mr-1.5 rounded-lg text-ink-3 hover:text-ink-2 hover:bg-subtle transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                </button>
                <div x-show="menu" x-cloak x-transition.origin.top.right
                    class="absolute right-0 mt-1 w-48 z-20 py-1 bg-surface rounded-xl shadow-card border border-line text-sm">
                    <button type="button" @click="menu = false; $dispatch('open-modal', 'contactor-{{ $contactor->id }}')"
                        class="w-full text-left px-3 py-2 text-ink hover:bg-subtle">
                        Editar
                    </button>
                    <button type="button"
                        @click="navigator.clipboard?.writeText(@js($contactor->entity_id)); copied = true; setTimeout(() => { copied = false; menu = false }, 900)"
                        class="w-full text-left px-3 py-2 text-ink hover:bg-subtle">
                        <span x-text="copied ? 'Copiado!' : 'Copiar entity_id'"></span>
                    </button>
                    <form method="POST" action="{{ route('home-assistant.destroy', $contactor) }}"
                        onsubmit="return confirm(@js('Remover o contator “' . $contactor->name . '”? Os espaços vinculados ficam sem contator e ele sai dos agendamentos.'))">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full text-left px-3 py-2 text-danger hover:bg-danger-soft">
                            Remover
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Estado + motivo --}}
        <div class="flex items-center gap-2 min-w-0">
            <span class="inline-flex items-center gap-1.5 text-sm font-bold {{ $state->on ? 'text-ok' : 'text-ink-2' }}">
                <span class="relative flex w-2 h-2">
                    @if($state->on)
                        <span class="absolute inline-flex h-full w-full rounded-full bg-ok opacity-75 animate-ping"></span>
                    @endif
                    <span class="relative inline-flex w-2 h-2 rounded-full {{ $state->on ? 'bg-ok' : 'bg-line' }}"></span>
                </span>
                {{ $state->on ? 'Ligado' : 'Desligado' }}
            </span>
            <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold shrink-0 {{ $badgeClass }}">{{ $badgeLabel }}</span>
        </div>
        <p class="-mt-2 text-xs text-ink-2 truncate" title="{{ $state->reason() }}">{{ $state->reason() }}</p>

        {{-- Linha do tempo --}}
        @include('home-assistant.partials.timeline', ['timeline' => $timeline, 'now' => $now])

        {{-- Espaços e agendamentos --}}
        <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
            @forelse($contactor->places as $place)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-subtle border border-line font-medium text-ink-2">
                    <svg class="w-3 h-3 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    {{ $place->name }}
                </span>
            @empty
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-warn-soft text-warn font-medium"
                    title="Vincule o contator a um espaço na tela de edição do espaço">
                    Nenhum espaço vinculado: reservas não acendem este contator
                </span>
            @endforelse
            @if($schedulesCount)
                <button type="button" @click="go('schedules')"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-grena-ink hover:bg-grena-tint font-medium transition">
                    {{ $schedulesCount }} {{ $schedulesCount === 1 ? 'agendamento' : 'agendamentos' }} →
                </button>
            @endif
        </div>
    </div>

    {{-- Controle --}}
    <form method="POST" action="{{ route('home-assistant.quick', $contactor) }}"
        class="px-5 py-3 border-t border-line bg-subtle rounded-b-2xl">
        @csrf
        <div class="grid grid-cols-3 p-1 gap-1 rounded-xl bg-subtle text-xs font-semibold" role="group" aria-label="Controle do contator">
            <button type="submit" formaction="{{ route('home-assistant.quick.clear', $contactor) }}"
                @disabled($control === 'auto')
                title="Segue agendamentos e reservas"
                class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg transition
                    {{ $control === 'auto' ? 'bg-surface text-ink shadow-card cursor-default' : 'text-ink-2 hover:text-ink' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Automático
            </button>
            <button type="submit" name="state" value="on"
                @disabled($control === 'on')
                title="Liga agora e mantém ligado até o fim do dia"
                class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg transition
                    {{ $control === 'on' ? 'bg-ink text-white shadow-card cursor-default' : 'text-ink-2 hover:text-ink' }}">
                Ligado
            </button>
            <button type="submit" name="state" value="off"
                @disabled($control === 'off')
                title="Desliga agora e mantém desligado até o fim do dia, mesmo com reserva"
                class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg transition
                    {{ $control === 'off' ? 'bg-ink text-canvas shadow-card cursor-default' : 'text-ink-2 hover:text-ink' }}">
                Desligado
            </button>
        </div>
    </form>
</article>
