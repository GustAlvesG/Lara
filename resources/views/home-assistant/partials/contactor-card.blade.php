@php
    $now       = now();
    $effective = $contactor->effectiveOverride($now);
    $quick     = $contactor->overrides->first(fn ($o) => $o->is_active && $o->is_quick);
    $state     = $effective?->resolvedState($now);

    [$dot, $statusLabel, $statusClass] = match(true) {
        $effective && $state === true  => ['bg-green-500', 'Ligado',   'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
        $effective && $state === false => ['bg-red-500',   'Desligado','bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
        default                        => ['bg-gray-300',  'Padrão',   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'],
    };

    $linkedSchedules = $contactor->overrides->filter(fn ($o) => $o->is_active && ! $o->is_quick);
@endphp

<div x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow sm:rounded-2xl p-5 flex flex-col gap-4 border border-gray-100 dark:border-gray-700">

    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <span class="w-2.5 h-2.5 rounded-full {{ $dot }} shrink-0"></span>
            <h3 class="font-bold text-gray-900 dark:text-white text-base truncate">{{ $contactor->name }}</h3>
        </div>
        <span class="text-xs px-2 py-1 rounded-full font-medium shrink-0 {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    {{-- Ações rápidas --}}
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('home-assistant.quick', $contactor) }}">
            @csrf
            <input type="hidden" name="state" value="on">
            <button type="submit"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Ligar
            </button>
        </form>

        <form method="POST" action="{{ route('home-assistant.quick', $contactor) }}">
            @csrf
            <input type="hidden" name="state" value="off">
            <button type="submit"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Desligar
            </button>
        </form>

        @if($quick)
            <form method="POST" action="{{ route('home-assistant.quick.clear', $contactor) }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.5 7.5 0 0112 4.5c2.7 0 5.08 1.43 6.418 3.5M19.418 15A7.5 7.5 0 0112 19.5a7.47 7.47 0 01-6.418-3.5"/></svg>
                    Padrão
                </button>
            </form>
        @endif

        <button @click="open = !open"
            class="ml-auto inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
            Detalhes
            <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
    </div>

    {{-- Detalhes (expansível) --}}
    <div x-show="open" x-cloak x-transition class="space-y-3 pt-3 border-t border-gray-100 dark:border-gray-700">

        {{-- Entity ID --}}
        <p class="text-xs text-gray-400 dark:text-gray-500 font-mono break-all">{{ $contactor->entity_id }}</p>

        {{-- Locais vinculados --}}
        @if($contactor->places->isNotEmpty())
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">Locais: </span>
                {{ $contactor->places->pluck('name')->join(', ') }}
            </div>
        @endif

        {{-- Override vigente --}}
        @if($effective)
            <div class="rounded-lg px-3 py-2 text-xs border
                {{ $effective->is_quick
                    ? 'bg-gray-50 dark:bg-gray-700/40 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300'
                    : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300' }}">
                <div class="flex items-center gap-1 font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ $effective->is_quick ? 'Ação rápida' : 'Sob agendamento' }}: {{ $effective->name }}
                </div>
            </div>
        @endif

        {{-- Agendamentos vinculados --}}
        @if($linkedSchedules->isNotEmpty())
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">Agendamentos: </span>
                {{ $linkedSchedules->pluck('name')->join(', ') }}
            </div>
        @endif

        {{-- Editar / remover --}}
        <div class="flex justify-end gap-2 pt-1">
            <button onclick="document.getElementById('modal-edit-{{ $contactor->id }}').classList.remove('hidden')"
                class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                Editar contator
            </button>
            <form method="POST" action="{{ route('home-assistant.destroy', $contactor) }}"
                onsubmit="return confirm('Remover este contator?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition">Remover</button>
            </form>
        </div>
    </div>
</div>

{{-- Modal: editar contator --}}
<div id="modal-edit-{{ $contactor->id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Editar Contator</h3>
            <button onclick="document.getElementById('modal-edit-{{ $contactor->id }}').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('home-assistant.update', $contactor) }}" class="p-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                <input type="text" name="name" required value="{{ $contactor->name }}"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Entity ID</label>
                <input type="text" name="entity_id" required value="{{ $contactor->entity_id }}"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('modal-edit-{{ $contactor->id }}').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 text-sm bg-red-800 hover:bg-red-700 text-white rounded-lg font-medium">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>
