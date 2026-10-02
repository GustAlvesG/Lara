@php
    $plateValue = $plate ?? old('plate');
    $dateValue = $datetime ?? old('datetime', date('Y-m-d'));
@endphp

<section class="overflow-hidden rounded-card bg-surface shadow-card">
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" style="{{ \App\View\AreaColor::style('portaria') }}">
            <x-icon name="search" class="h-5 w-5" />
        </span>
        <div>
            <h2 class="font-display text-lg font-semibold leading-tight tracking-tight text-ink">Buscar veículo</h2>
            <p class="text-sm text-ink-2">Informe a placa e a data do acesso.</p>
        </div>
    </div>

    {{-- POST de propósito (a rota é parking.show): a placa não fica na URL nem
         no histórico do navegador da portaria. --}}
    <form action="{{ route('parking.show') }}" method="POST" class="p-5"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-12">
            <div class="md:col-span-6">
                <x-input-label for="plate" value="Placa do veículo" class="mb-1.5" />
                <div class="relative">
                    <x-icon name="car" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-3" />
                    <input id="plate" name="plate" required autofocus autocomplete="off"
                           value="{{ $plateValue }}"
                           placeholder="ABC1D23"
                           maxlength="8"
                           oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                           class="h-12 w-full rounded-xl border border-line-strong bg-surface pl-11 pr-4 font-mono text-lg font-semibold uppercase tracking-[0.2em] text-ink shadow-none transition placeholder:font-sans placeholder:text-base placeholder:font-normal placeholder:tracking-normal placeholder:text-ink-3 focus:border-grena focus:ring-4 focus:ring-grena-tint">
                </div>
            </div>

            <div class="md:col-span-3">
                <x-input-label for="datetime" value="Data do acesso" class="mb-1.5" />
                <input type="date" id="datetime" name="datetime" value="{{ $dateValue }}"
                       class="h-12 w-full rounded-xl border border-line-strong bg-surface px-4 font-mono text-ink shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint">
            </div>

            <div class="md:col-span-3">
                <button type="submit" x-bind:disabled="loading"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-grena px-6 font-bold text-white transition hover:bg-grena-hover disabled:cursor-wait disabled:opacity-60">
                    <x-icon name="search" class="h-5 w-5" x-show="!loading" />
                    <svg x-show="loading" x-cloak class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="loading ? 'Buscando...' : 'Buscar'">Buscar</span>
                </button>
            </div>
        </div>
    </form>
</section>
