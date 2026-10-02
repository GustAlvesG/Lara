<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Time') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('placar.times.store') }}" method="POST">
            @csrf
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados do Time</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Equipe <span class="text-danger">*</span></label>
                        <select name="equipe_id" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($equipes as $equipe)
                                <option value="{{ $equipe->id }}" @selected((string) old('equipe_id') === (string) $equipe->id)>{{ $equipe->nome }}</option>
                            @endforeach
                        </select>
                        @error('equipe_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Modalidade <span class="text-danger">*</span></label>
                        <select name="modalidade_id" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($modalidades as $modalidade)
                                <option value="{{ $modalidade->id }}" @selected((string) old('modalidade_id') === (string) $modalidade->id)>{{ $modalidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('modalidade_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Categoria <span class="text-danger">*</span></label>
                        <input type="text" name="categoria" value="{{ old('categoria', 'Adulto') }}" required
                            list="categorias-existentes" autocomplete="off"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @include('placar.times.partials.categorias-datalist')
                        @error('categoria')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Nome de exibição</label>
                        <input type="text" name="nome_exibicao" value="{{ old('nome_exibicao') }}"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        <p class="mt-1 text-xs text-ink-3">Se vazio, é montado a partir da equipe + categoria.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('placar.times.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
