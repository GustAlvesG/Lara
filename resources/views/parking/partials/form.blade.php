@php
    $plateValue = $plate ?? old('plate');
    $dateValue = $datetime ?? old('datetime', date('Y-m-d'));
    // Na Busca, os números do dia vêm ao lado; no resultado ("Nova busca"), não.
    $withTotals = isset($todayParkingCount);
@endphp

{{--
    Busca de placa (identidade do SIV desde 11/11/2026): faixa escura de
    câmera e a própria placa Mercosul como campo, dentro do visor. A linha de
    leitura passa uma vez ao abrir e de novo ao focar (siv-scan, app.css).
--}}
<section class="siv-night relative isolate overflow-hidden rounded-[28px] shadow-card" aria-labelledby="siv-busca-titulo">
    <div class="auth-enter grid grid-cols-1 gap-8 p-6 sm:p-10 {{ $withTotals ? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] lg:items-center lg:gap-12' : '' }}">

        {{-- POST de propósito (a rota é parking.show): a placa não fica na URL
             nem no histórico do navegador da portaria. --}}
        <form action="{{ route('parking.show') }}" method="POST" class="flex min-w-0 flex-col gap-6"
              x-data="{ loading: false }" @submit="loading = true">
            @csrf

            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">Leitura de placas</p>
                <h2 id="siv-busca-titulo" class="mt-2 font-display text-[26px] font-semibold leading-tight tracking-tight sm:text-[34px]">Qual placa você procura?</h2>
            </div>

            <div class="siv-viewfinder w-full max-w-[460px]">
                <label for="plate" class="siv-plate-input relative block overflow-hidden rounded-2xl border-[3px] border-[#1a1a1a] bg-white text-[#111]">
                    <span class="block bg-plate-band py-1 text-center font-sans text-[11px] font-bold tracking-[0.42em] text-white" aria-hidden="true">BRASIL</span>
                    <span class="sr-only">Placa do veículo</span>
                    <input id="plate" name="plate" required autofocus autocomplete="off" spellcheck="false"
                           value="{{ $plateValue }}"
                           placeholder="ABC1D23"
                           maxlength="8"
                           oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')"
                           class="block w-full border-0 bg-transparent px-4 pb-3 pt-2 text-center font-mono text-[40px] font-semibold uppercase leading-none tracking-[0.12em] text-[#111] placeholder:text-[#111]/20 focus:ring-0 sm:text-[54px]">
                    <span class="siv-scan" aria-hidden="true"></span>
                </label>
            </div>

            <div class="flex w-full max-w-[460px] flex-col gap-3 sm:flex-row sm:items-end">
                <div class="sm:w-48">
                    <label for="datetime" class="mb-1.5 block text-xs font-bold text-white/70">Data do acesso</label>
                    <input type="date" id="datetime" name="datetime" value="{{ $dateValue }}"
                           class="h-12 w-full rounded-xl border-white/20 bg-white/10 px-4 font-mono text-white shadow-none transition [color-scheme:dark] focus:border-white focus:bg-white/15 focus:ring-4 focus:ring-white/20">
                </div>

                <button type="submit" x-bind:disabled="loading"
                        class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-white px-6 font-bold text-[rgb(var(--siv-night))] transition hover:-translate-y-px hover:bg-white/90 active:translate-y-0 active:scale-[.98] disabled:cursor-wait disabled:opacity-60 motion-reduce:transform-none">
                    <x-icon name="search" class="h-5 w-5" x-show="!loading" />
                    <svg x-show="loading" x-cloak class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span x-text="loading ? 'Buscando...' : 'Buscar acessos'">Buscar acessos</span>
                </button>
            </div>
        </form>

        @if ($withTotals)
            @include('parking.partials.dashTotals')
        @endif
    </div>
</section>
