<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Jogo') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form
            action="{{ route('placar.jogos.store') }}" method="POST"
            x-data="{ modalidadeId: '{{ old('modalidade_id') }}' }"
        >
            @csrf
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados do Jogo</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Modalidade <span class="text-danger">*</span></label>
                        <select name="modalidade_id" x-model="modalidadeId" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($modalidades as $modalidade)
                                <option value="{{ $modalidade->id }}">{{ $modalidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('modalidade_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-ink-3">Escolha antes dos times — as listas abaixo filtram por ela.</p>
                    </div>

                    <div></div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Time da casa <span class="text-danger">*</span></label>
                        <select name="time_casa_id" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($times as $time)
                                <option value="{{ $time->id }}" x-show="!modalidadeId || modalidadeId == {{ $time->modalidade_id }}" @selected((string) old('time_casa_id') === (string) $time->id)>
                                    {{ $time->nomeExibicaoResolvido() }} ({{ $time->equipe->nome }} · {{ $time->modalidade->nome }})
                                </option>
                            @endforeach
                        </select>
                        @error('time_casa_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Time visitante <span class="text-danger">*</span></label>
                        <select name="time_fora_id" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($times as $time)
                                <option value="{{ $time->id }}" x-show="!modalidadeId || modalidadeId == {{ $time->modalidade_id }}" @selected((string) old('time_fora_id') === (string) $time->id)>
                                    {{ $time->nomeExibicaoResolvido() }} ({{ $time->equipe->nome }} · {{ $time->modalidade->nome }})
                                </option>
                            @endforeach
                        </select>
                        @error('time_fora_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Competição</label>
                        <select name="competicao_id" class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Nenhuma</option>
                            @foreach($competicoes as $competicao)
                                <option value="{{ $competicao->id }}" @selected((string) old('competicao_id') === (string) $competicao->id)>{{ $competicao->nome }} ({{ $competicao->temporada }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Data e hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora') }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('data_hora')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Local</label>
                        <input type="text" name="local" value="{{ old('local') }}"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('placar.jogos.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
