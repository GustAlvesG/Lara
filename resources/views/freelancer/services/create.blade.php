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

            {{-- Acima do limite de 7 dias, quem libera é o coordenador do
                 Comercial — com o PIN dele, ou com um código enviado ao e-mail
                 dele quando não está presente.

                 O campo escondido mantém o bloco na tela mesmo quando o pedido
                 de código volta por erro de validação, que não reenvia o flash. --}}
            @if(session('confirm_weekly_limit') || old('weekly_limit_pending'))
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border-2 border-amber-400 overflow-hidden">
                    <input type="hidden" name="weekly_limit_pending" value="1">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-amber-50/60 dark:bg-amber-900/20">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Liberação do coordenador do setor Comercial</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Somente o coordenador do setor Comercial pode liberar este registro. Presencialmente,
                            ele informa a <b>própria matrícula</b> e o <b>próprio PIN</b>. Se não estiver no local,
                            envie um <b>código por e-mail</b> para ele ditar.
                        </p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Matrícula do coordenador <span class="text-red-500">*</span></label>
                            <input type="text" name="coordinator_matricula" value="{{ old('coordinator_matricula') }}"
                                inputmode="numeric" autocomplete="off" required
                                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            {{-- Mesmo formulário, outro destino: nada do que já foi
                                 preenchido se perde ao pedir o código. --}}
                            <button type="submit" formaction="{{ route('freelancer-services.weekly-limit-code') }}"
                                formnovalidate
                                class="mt-2 text-sm font-bold text-amber-700 dark:text-amber-400 hover:underline">
                                Coordenador ausente? Enviar código por e-mail
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">PIN ou código do coordenador <span class="text-red-500">*</span></label>
                            <input type="password" name="coordinator_pin" inputmode="numeric" maxlength="6"
                                pattern="[0-9]{6}" autocomplete="new-password" required placeholder="••••••"
                                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white tracking-[0.4em]">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                6 dígitos: o PIN dele, ou o código que ele recebeu por e-mail. Não fica guardado na tela.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-services.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                @if(session('confirm_weekly_limit') || old('weekly_limit_pending'))
                    <button type="submit" name="confirm_weekly_limit" value="1" class="px-6 py-3 bg-amber-500 text-white rounded-xl font-bold shadow-lg hover:bg-amber-600 transition">Liberar e registrar</button>
                @else
                    <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">Registrar</button>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
