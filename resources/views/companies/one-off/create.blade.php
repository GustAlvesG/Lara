<x-app-layout>

    <div>
        <x-crud.form :formRoute="route('company.one-off.store')" :formMethod="'POST'" :hasImageSection="false">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <a href="{{ route('company.one-off.index') }}"
                       class="p-2 bg-white dark:bg-gray-700 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Nova Liberação Pontual</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Para o caso extraordinário, sem tempo para o cadastro formal.</p>
                    </div>
                </div>

                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl px-5 py-4 text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-bold">Vale para <u>uma entrada</u>, somente hoje ({{ today()->format('d/m/Y') }}).</p>
                    <p class="mt-1">Não vincula a pessoa a nenhuma empresa. Na portaria, basta digitar o CPF no Monitor de Acesso.
                        Se ela for voltar outras vezes, faça o cadastro formal de terceirizado.</p>
                </div>
            </x-slot>

            <x-slot name="fields">

                {{-- A mesma captura do cadastro de freelancer (câmera ou importação), sem foto atual. --}}
                @include('freelancer.freelancers.partials.photo', ['freelancer' => null])
                <p class="-mt-4 text-center text-xs text-gray-400 dark:text-gray-500">Foto opcional.</p>

                <div>
                    <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome Completo</label>
                    <input type="text" id="name" name="name" required maxlength="255" placeholder="Ex: João Silva"
                           value="{{ old('name') }}"
                           class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="cpf" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">CPF</label>
                    <input type="text" id="cpf" name="cpf" required inputmode="numeric" maxlength="14" autocomplete="off"
                           placeholder="000.000.000-00" value="{{ old('cpf', $cpf) }}"
                           class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 font-mono">
                    @error('cpf')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">É por ele que a portaria encontra a liberação.</p>
                </div>

                <div>
                    <label for="reason" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Motivo</label>
                    <textarea id="reason" name="reason" required maxlength="1000" rows="3"
                              placeholder="Ex: Técnico da empresa de elevadores — pane no elevador social. Autorizado por telefone pela Gerência."
                              class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Aparece para a portaria e fica no histórico.</p>
                </div>

            </x-slot>

        </x-crud.form>
    </div>

    <script>
        (function () {
            const field = document.getElementById('cpf');

            function format(value) {
                return value.replace(/\D/g, '').slice(0, 11)
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }

            field.value = format(field.value);
            field.addEventListener('input', () => field.value = format(field.value));
        })();
    </script>

</x-app-layout>
