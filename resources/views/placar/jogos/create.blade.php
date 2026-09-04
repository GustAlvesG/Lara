<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Novo Jogo') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form
            action="{{ route('placar.jogos.store') }}" method="POST"
            x-data="{ modalidadeId: '{{ old('modalidade_id') }}' }"
        >
            @csrf
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados do Jogo</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Modalidade <span class="text-red-500">*</span></label>
                        <select name="modalidade_id" x-model="modalidadeId" required class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($modalidades as $modalidade)
                                <option value="{{ $modalidade->id }}">{{ $modalidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('modalidade_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Escolha antes dos times — as listas abaixo filtram por ela.</p>
                    </div>

                    <div></div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Time da casa <span class="text-red-500">*</span></label>
                        <select name="time_casa_id" required class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($times as $time)
                                <option value="{{ $time->id }}" x-show="!modalidadeId || modalidadeId == {{ $time->modalidade_id }}" @selected((string) old('time_casa_id') === (string) $time->id)>
                                    {{ $time->nomeExibicaoResolvido() }} ({{ $time->equipe->nome }} · {{ $time->modalidade->nome }})
                                </option>
                            @endforeach
                        </select>
                        @error('time_casa_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Time visitante <span class="text-red-500">*</span></label>
                        <select name="time_fora_id" required class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($times as $time)
                                <option value="{{ $time->id }}" x-show="!modalidadeId || modalidadeId == {{ $time->modalidade_id }}" @selected((string) old('time_fora_id') === (string) $time->id)>
                                    {{ $time->nomeExibicaoResolvido() }} ({{ $time->equipe->nome }} · {{ $time->modalidade->nome }})
                                </option>
                            @endforeach
                        </select>
                        @error('time_fora_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Competição</label>
                        <select name="competicao_id" class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Nenhuma</option>
                            @foreach($competicoes as $competicao)
                                <option value="{{ $competicao->id }}" @selected((string) old('competicao_id') === (string) $competicao->id)>{{ $competicao->nome }} ({{ $competicao->temporada }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Data e hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora') }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('data_hora')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Local</label>
                        <input type="text" name="local" value="{{ old('local') }}"
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('placar.jogos.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
