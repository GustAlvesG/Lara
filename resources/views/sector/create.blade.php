<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('sectors.store') }}" method="POST">
            @csrf

            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('sectors.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Novo Setor</h1>
                        <p class="text-ink-2 font-medium">O nome do setor deve corresponder exatamente ao departamento no Banco de Horas.</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('sectors.index') }}" class="px-6 py-3 bg-surface text-ink rounded-xl font-bold shadow-card hover:bg-subtle border border-line transition">
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                        Criar Setor
                    </button>
                </div>
            </div>

            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados do Setor</h2>
                </div>

                <div class="p-6 space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-bold text-ink mb-1">Nome do Setor <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink"
                            placeholder="Ex: TI, Financeiro, RH...">
                        <p class="mt-1 text-xs text-ink-2">Deve corresponder exatamente ao nome do departamento no Banco de Horas.</p>
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-bold text-ink mb-1">Descrição</label>
                        <input type="text" name="description" id="description" value="{{ old('description') }}"
                            class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink"
                            placeholder="Descrição opcional do setor">
                        @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

</x-app-layout>
