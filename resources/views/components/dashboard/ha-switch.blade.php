@props(['contactor', 'state'])

{{-- $state: App\Services\HomeAssistant\ContactorState, o mesmo cálculo que o Home Assistant recebe --}}
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border p-4 flex items-center justify-between gap-3
    {{ $state->on ? 'border-amber-200 dark:border-amber-800/60' : 'border-gray-100 dark:border-gray-700' }}">

    <div class="flex items-center gap-3 min-w-0">
        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
            {{ $state->on ? 'bg-amber-400 text-white shadow-[0_0_14px_rgba(251,191,36,.5)]' : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        </span>
        <div class="min-w-0">
            <span class="block font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $contactor->name }}</span>
            <span class="block text-[11px] text-gray-400 dark:text-gray-500 truncate" title="{{ $state->reason() }}">
                {{ $state->reason() }}
            </span>
            @if($state->isManual())
                <form method="POST" action="{{ route('home-assistant.quick.clear', $contactor) }}">
                    @csrf
                    <button type="submit" class="text-[11px] font-semibold text-red-800 dark:text-red-400 hover:underline">
                        Voltar ao automático
                    </button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('home-assistant.quick', $contactor) }}" class="shrink-0">
        @csrf
        <input type="hidden" name="state" value="{{ $state->on ? 'off' : 'on' }}">
        <label class="relative inline-flex items-center cursor-pointer"
            title="{{ $state->on ? 'Desligar até o fim do dia' : 'Ligar até o fim do dia' }}">
            <input type="checkbox" class="sr-only peer" @checked($state->on)
                aria-label="{{ ($state->on ? 'Desligar ' : 'Ligar ') . $contactor->name }}"
                onchange="this.closest('form').submit()">
            <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer-checked:bg-amber-400
                peer-focus-visible:ring-2 peer-focus-visible:ring-red-800
                after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
        </label>
    </form>
</div>
