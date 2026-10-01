@props(['contactor', 'state'])

{{-- $state: App\Services\HomeAssistant\ContactorState, o mesmo cálculo que o Home Assistant recebe --}}
<div class="flex items-center justify-between gap-3 rounded-card bg-surface p-4 shadow-card {{ $state->on ? 'ring-2 ring-ok/40' : '' }}">

    <div class="flex min-w-0 items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl {{ $state->on ? 'bg-ok-soft text-ok' : 'bg-subtle text-ink-3' }}">
            <x-icon name="bulb" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <span class="block truncate font-bold text-ink">{{ $contactor->name }}</span>
            <span class="block truncate text-xs text-ink-3" title="{{ $state->reason() }}">
                {{ $state->on ? 'Ligado' : 'Desligado' }} · {{ $state->reason() }}
            </span>
            @if($state->isManual())
                <form method="POST" action="{{ route('home-assistant.quick.clear', $contactor) }}">
                    @csrf
                    <button type="submit" class="text-xs font-bold text-grena-ink hover:underline">
                        Voltar ao automático
                    </button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('home-assistant.quick', $contactor) }}" class="shrink-0">
        @csrf
        <input type="hidden" name="state" value="{{ $state->on ? 'off' : 'on' }}">
        <label class="relative inline-flex cursor-pointer items-center"
            title="{{ $state->on ? 'Desligar até o fim do dia' : 'Ligar até o fim do dia' }}">
            <input type="checkbox" class="peer sr-only" @checked($state->on)
                aria-label="{{ ($state->on ? 'Desligar ' : 'Ligar ') . $contactor->name }}"
                onchange="this.closest('form').submit()">
            <div class="h-6 w-11 rounded-full bg-line-strong transition peer-checked:bg-ok
                peer-focus-visible:ring-4 peer-focus-visible:ring-grena-tint
                after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white
                after:transition-all after:content-[''] peer-checked:after:translate-x-5"></div>
        </label>
    </form>
</div>
