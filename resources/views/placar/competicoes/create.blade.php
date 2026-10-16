<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Nova Competição') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('placar.competicoes.store') }}" method="POST">
            @csrf
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados da Competição</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" value="{{ old('nome') }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('nome')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
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
                        <label class="block text-sm font-bold text-ink mb-1">Temporada <span class="text-danger">*</span></label>
                        <input type="number" name="temporada" value="{{ old('temporada', now()->year) }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('temporada')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            @include('placar.partials.botoes-cadastro', ['voltar' => route('placar.competicoes.index')])
        </form>
    </div>
</div>
</x-app-layout>
