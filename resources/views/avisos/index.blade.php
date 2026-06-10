<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Avisos e Lembretes
            </h2>
            @if(auth()->user()->can('manage avisos') || auth()->user()->hasRole('admin'))
                <x-primary-button-a href="{{ route('avisos.create') }}">
                    + Novo Aviso
                </x-primary-button-a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Avisos Ativos --}}
            @if($avisos->isEmpty())
                <div class="p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg text-center text-gray-500 dark:text-gray-400">
                    Nenhum aviso ativo no momento.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($avisos as $aviso)
                        @include('avisos.partials.card', ['aviso' => $aviso])
                    @endforeach
                </div>
            @endif

            {{-- Avisos Expirados --}}
            @if($expirados->isNotEmpty())
                <div x-data="{ open: false }">
                    <button @click="open = !open"
                        class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                        <svg class="w-4 h-4" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.2s">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Avisos expirados ({{ $expirados->count() }})
                    </button>
                    <div x-show="open" x-transition class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($expirados as $aviso)
                            @include('avisos.partials.card', ['aviso' => $aviso, 'expired' => true])
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
