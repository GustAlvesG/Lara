{{-- Título da agenda: troca de dia (setas e calendário), PDF do dia e configurações. --}}
@php
    $dayButton = 'grid h-9 w-9 place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink';
@endphp
<x-page-title title="Reservas">
    Ocupação e horários disponíveis do dia, por modalidade. Escolha os horários livres de um local e identifique o sócio.

    <x-slot:actions>
        <div class="flex items-center gap-1 rounded-full border border-line-strong bg-surface p-1">
            <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' -1 day'))]) }}" class="{{ $dayButton }}" aria-label="Dia anterior">
                <x-icon name="arrow-right" class="h-4 w-4 rotate-180" />
            </a>
            <form action="{{ url()->current() }}" method="GET">
                <label for="report-date" class="sr-only">Data da agenda</label>
                <input id="report-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                       class="h-9 cursor-pointer rounded-full border-0 bg-transparent px-2 font-mono text-sm font-semibold text-ink focus:ring-0">
            </form>
            <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' +1 day'))]) }}" class="{{ $dayButton }}" aria-label="Próximo dia">
                <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
        <x-secondary-button type="button" onclick="generatePDFTable({{ json_encode($modalities) }})">
            <x-icon name="download" /> PDF do dia
        </x-secondary-button>
        <x-secondary-button-a href="{{ route('place-group.index') }}">
            <x-icon name="sliders" /> Configurações
        </x-secondary-button-a>
    </x-slot:actions>
</x-page-title>
