<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Novo Serviço') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        @include('freelancer.partials.import-card', [
            'action' => route('freelancer-services.import'),
            'templateRoute' => route('freelancer-services.import.template'),
            'columns' => $importColumns,
            'hint' => 'O freelancer é localizado pelo CPF, que já deve estar cadastrado. A função precisa ser escrita com o mesmo nome usado no cadastro de Funções. Valor e horas pagas são calculados na importação.',
        ])

        @if(session('confirm_weekly_limit'))
            <div class="mb-6 bg-amber-500 border border-amber-400 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center gap-4">
                <div class="bg-white/20 p-2 rounded-full shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-extrabold text-lg leading-none">Atenção</p>
                    <p class="text-sm text-amber-50 mt-1">{{ session('confirm_weekly_limit') }}</p>
                </div>
            </div>
        @endif

        <form action="{{ route('freelancer-services.store') }}" method="POST">
            @csrf
            @include('freelancer.services.partials.form', ['locked' => false])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-services.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                @if(session('confirm_weekly_limit'))
                    <button type="submit" name="confirm_weekly_limit" value="1" class="px-6 py-3 bg-amber-500 text-white rounded-xl font-bold shadow-lg hover:bg-amber-600 transition">Confirmar e registrar</button>
                @else
                    <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">Registrar</button>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
