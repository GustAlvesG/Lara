@props(['contactor'])

@php
    $now       = now();
    $effective = $contactor->effectiveOverride($now);
    $isOn      = $effective?->resolvedState($now) === true;

    // Motivo do estado atual
    $reason = match (true) {
        $effective && $effective->is_quick => 'Acionamento manual',
        (bool) $effective                  => 'Agendamento de acionamento: ' . $effective->name,
        default                            => 'Agendamento de espaço (reservas)',
    };
@endphp

<form method="POST" action="{{ route('home-assistant.quick', $contactor) }}"
    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex items-center justify-between gap-3">
    @csrf
    <input type="hidden" name="state" value="{{ $isOn ? 'off' : 'on' }}">

    <div class="min-w-0">
        <span class="block font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $contactor->name }}</span>
        <span class="block text-[11px] text-gray-400 dark:text-gray-500 truncate">{{ __($reason) }}</span>
    </div>

    {{-- Interruptor (checkbox estilizado) --}}
    <label class="relative inline-flex items-center cursor-pointer shrink-0">
        <input type="checkbox" class="sr-only peer" {{ $isOn ? 'checked' : '' }}
            onchange="this.closest('form').submit()">
        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer-checked:bg-green-500
            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
            after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
    </label>
</form>
