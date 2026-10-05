{{--
    Financeiro, lista plana: "todos os contratos" e "contratos sem lote".

    Existem para que nada de pagável fique invisível por não estar num lote, e
    para preservar a busca transversal e a seleção entre lotes que a tela antiga
    permitia. A baixa daqui volta para esta mesma tela (sem `$batch`).
--}}
<x-app-layout>
<div class="py-6">
    {{-- Tabela larga: sem o cap de largura ela deixa de ser cortada. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="{{ route('freelancer-services.finance') }}"
               class="inline-flex items-center gap-1 text-sm font-semibold text-ink-2 hover:text-grena-ink transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Todos os lotes
            </a>
        </div>

        <div class="mb-8">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $titulo }}</h1>
            <p class="text-ink-2 font-medium">{{ $subtitulo }}</p>
        </div>

        @include('freelancer.services.partials.finance-pix-warning')

        @include('freelancer.services.partials.tabs', ['activeTab' => 'freelancer-services.finance'])

        @include('partials.alerts')

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-surface rounded-2xl shadow-card border border-line p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-ink-3">A pagar</p>
                <p class="mt-1 text-2xl font-extrabold text-ink">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Pago</p>
                <p class="mt-1 text-2xl font-extrabold text-ok">R$ {{ number_format($paidTotal, 2, ',', '.') }}</p>
            </div>
        </div>

        @include('freelancer.services.partials.finance-table')
    </div>
</div>
</x-app-layout>
